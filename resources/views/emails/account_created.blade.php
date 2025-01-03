<!DOCTYPE html>
<html>
<head>
    <title>Account Created</title>
</head>
<body>
    <h1>Welcome, {{ $user->name }}!</h1>
    <p>Your account has been successfully created with the following details:</p>
    <ul>
        <li>Email: {{ $user->email }}</li>
    </ul>
    <p>Thank you for joining us!</p>
</body>
</html>
