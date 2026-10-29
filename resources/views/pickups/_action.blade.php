<form method="POST" action="{{ route('pickups.updateStatus', $task) }}"
      @if(! empty($confirm)) onsubmit="return confirm(@js($confirm))" @endif>
    @csrf @method('PATCH')
    <input type="hidden" name="status" value="{{ $status }}">
    <button class="{{ $class }}">{{ $label }}</button>
</form>