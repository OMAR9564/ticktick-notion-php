<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>TickTick - Notion Integration</title>
</head>

<body>
    <div class="container mt-5">
        <h1 class="mb-4">TickTick - Notion Integration</h1>
        <?php if (session()->getFlashdata('sync_success')): ?>
            <div class="alert alert-success">Tasks synced successfully</div>
        <?php elseif (session()->getFlashdata('sync_error')): ?>
            <div class="alert alert-danger">Error: <?= session()->getFlashdata('sync_error') ?></div>
        <?php endif; ?>
        <div class="mb-4">
            <h3>Integration Status:</h3>
            <?php if (session()->get('integration_active')): ?>
                <div class="alert alert-success">Integration is Active</div>
            <?php else: ?>
                <div class="alert alert-danger">Integration is Inactive</div>
                <a href="<?= base_url('/activate-integration') ?>" class="btn btn-primary">Activate Integration</a>
            <?php endif; ?>
        </div>
        <div class="mb-4">
            <h3>Sync Options:</h3>
            <form action="<?= base_url('/sync-tasks') ?>" method="post">
                <label for="syncInterval" class="form-label">Sync Interval:</label>
                <select class="form-select" id="syncInterval" name="syncInterval">
                    <option value="5">5 seconds</option>
                    <option value="10">10 seconds</option>
                    <option value="30">30 seconds</option>
                    <option value="60">1 minute</option>
                    <option value="180">3 minutes</option>
                    <option value="300">5 minutes</option>
                    <option value="600">10 minutes</option>
                    <option value="1800">30 minutes</option>
                    <option value="3600">1 hour</option>
                </select>
                <button type="submit" class="btn btn-success mt-3">Sync Now</button>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>