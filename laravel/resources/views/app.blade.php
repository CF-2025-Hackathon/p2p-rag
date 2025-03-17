<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Chat App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #ffffff; /* Fundal alb */
        }

        .chat-container {
            max-width: 1024px;
            margin: 50px auto;
            background: #f9f7fc; /* Mov foarte pal */
            border-radius: 15px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            padding: 15px;
        }

        .chat-header {
            background: linear-gradient(to right, #b993d6, #8ca6db); /* Degradé mov-albăstrui */
            color: white;
            padding: 15px;
            text-align: center;
            font-size: 1.3rem;
            border-radius: 15px 15px 0 0;
            font-weight: bold;
        }

        .chat-footer {
            background: #ddd7ef; /* Mov foarte deschis */
            color: #4b0082;
            text-align: center;
            padding: 10px;
            border-radius: 0 0 15px 15px;
            font-size: 0.9rem;
        }
    </style>
    @livewireStyles
</head>
<body class="bg-body bg-gradient">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@livewireScripts

<div class="chat-container">
    <div class="chat-header">
        <i class="bi bi-chat-dots-fill"></i> Live Chat
    </div>

    <div class="p-3">
        @yield('content')
    </div>

    <div class="chat-footer">
        &copy; {{ date('Y') }} Live Chat. All rights reserved.
    </div>
</div>

</body>
</html>
