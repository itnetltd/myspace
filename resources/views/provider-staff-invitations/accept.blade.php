<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accept provider staff invitation</title>
</head>
<body>
    <main>
        <h1>Provider staff invitation</h1>
        <p>You are signed in as {{ auth()->user()->email }}. Accepting will add this account to the invited provider company.</p>
        <form method="POST" action="{{ route('provider-staff-invitations.accept', ['token' => $token]) }}">
            @csrf
            <button type="submit">Accept invitation</button>
        </form>
    </main>
</body>
</html>
