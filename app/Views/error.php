<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Hata</title>
</head>
<body class="bg-light d-flex flex-column justify-content-center align-items-center vh-100">
    <div class="container text-center">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="text-danger">Hata!</h1>
                <p class="lead"><?= $message ?? 'Bilinmeyen bir hata oluştu.' ?></p>
                <a href="/" class="btn btn-primary mt-3">Ana Sayfaya Dön</a>
            </div>
        </div>
    </div>
</body>
</html>
