<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use App\Models\ActivityLog;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('roles')
            ->when($request->q, function ($query) use ($request) {
                $query->where(function ($sub) use ($request) {
                    $sub->where('name', 'like', "%{$request->q}%")
                        ->orWhere('email', 'like', "%{$request->q}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::pluck('name');
        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|exists:roles,name',
            'avatar' => 'nullable|image|max:2048',
        ]);

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'avatar' => $avatarPath,
        ]);

        $user->assignRole($data['role']);

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        $roles = Role::pluck('name');
        return view('users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|exists:roles,name',
            'avatar' => 'nullable|image|max:2048',
        ]);

        // Pemilik tidak boleh menurunkan role akunnya sendiri, supaya tidak terkunci dari sistem
        if ($user->id === Auth::id() && $data['role'] !== 'pemilik') {
            return back()->withInput()->with('error', 'Kamu tidak bisa mengubah role akunmu sendiri.');
        }

        $avatarPath = $user->avatar;
        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => ! empty($data['password']) ? Hash::make($data['password']) : $user->password,
            'avatar' => $avatarPath,
        ]);

                $oldRole = $user->roles->pluck('name')->first();
        $user->syncRoles([$data['role']]);

        if ($oldRole !== $data['role']) {
            ActivityLog::record('user_changed', 'mengubah role ' . $user->name . ' dari ' . ($oldRole ?? '-') . ' menjadi ' . $data['role']);
        }

        if (! empty($data['password'])) {
            ActivityLog::record('user_changed', 'mengganti kata sandi ' . $user->name);
        }

        return redirect()->route('users.index')->with('success', 'Data pengguna diperbarui.');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Kamu tidak bisa menghapus akunmu sendiri.');
        }

        try {
            $user->delete();
        } catch (QueryException $e) {
            // Akun yang sudah mencatat transaksi, pembayaran, atau pengeluaran tidak boleh dihapus
            return back()->with('error', 'Pengguna ini sudah punya riwayat pencatatan sehingga tidak bisa dihapus.');
        }

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        return redirect()->route('users.index')->with('success', 'Pengguna dihapus.');
    }
}