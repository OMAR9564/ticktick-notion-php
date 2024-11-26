<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Proje Görevleri</title>
</head>
<body class="p-4">
    <div class="container">
        <h1>Proje: <?= $project['name'] ?? 'Bilinmiyor' ?></h1>

        <!-- Tamamlanmamış Görevler -->
        <h2 class="mt-4">Tamamlanmamış Görevler</h2>
        <?php if (!empty($tasks['activeTasks'])): ?>
            <ul class="list-group">
                <?php foreach ($tasks['activeTasks'] as $task): ?>
                    <li class="list-group-item">
                        <strong><?= $task['title'] ?? 'Başlıksız Görev' ?></strong>
                        <p><?= $task['content'] ?? '' ?></p>
                        <small>Öncelik: <?= $task['priority'] ?? 'Bilinmiyor' ?> | Son Tarih: <?= $task['dueDate'] ?? 'Yok' ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="alert alert-info">Tamamlanmamış görev yok.</div>
        <?php endif; ?>

        <!-- Tamamlanmış Görevler -->
        <h2 class="mt-4">Tamamlanmış Görevler</h2>
        <?php if (!empty($tasks['completedTasks'])): ?>
            <ul class="list-group">
                <?php foreach ($tasks['completedTasks'] as $task): ?>
                    <li class="list-group-item">
                        <strong><?= $task['title'] ?? 'Başlıksız Görev' ?></strong>
                        <p><?= $task['content'] ?? '' ?></p>
                        <small>Tamamlanma Tarihi: <?= $task['completedTime'] ?? 'Yok' ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="alert alert-info">Tamamlanmış görev yok.</div>
        <?php endif; ?>

        <a href="/ticktick/projects" class="btn btn-secondary mt-4">Geri</a>
    </div>
</body>
</html>
