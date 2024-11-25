<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Görevler</title>
</head>
<body class="p-4">
    <h1>Görevler</h1>
    <pre><?= json_encode($tasks, JSON_PRETTY_PRINT) ?></pre>
</body>
</html>
