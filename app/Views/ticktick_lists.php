<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>TickTick Listeleri</title>
</head>
<body class="p-4">
    <h1>TickTick Listeleri</h1>
    <div class="list-group">
        <?php foreach ($lists as $list): ?>
            <a href="/ticktick/getProjectTasks/<?= $list['id'] ?>/data" class="list-group-item list-group-item-action">
                <?= $list['name'] ?>
            </a>
        <?php endforeach; ?>
    </div>
</body>
</html>
