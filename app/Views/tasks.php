<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TickTick Tasks</title>
</head>
<body>
    <h1>Uncompleted Tasks</h1>
    <ul>
        <?php foreach ($uncompleted as $task): ?>
            <li><?= esc($task['title']) ?></li>
        <?php endforeach; ?>
    </ul>

    <h1>Completed Tasks</h1>
    <ul>
        <?php foreach ($completed as $task): ?>
            <li><?= esc($task['title']) ?></li>
        <?php endforeach; ?>
    </ul>

    <h2>Add New Task</h2>
    <form action="/ticktick/add" method="post">
        <label for="title">Title:</label>
        <input type="text" name="title" id="title" required>
        <label for="list_id">List ID:</label>
        <input type="text" name="list_id" id="list_id">
        <button type="submit">Add Task</button>
    </form>
</body>
</html>
