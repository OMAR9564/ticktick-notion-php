<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Secure Login & Sync</title>
</head>

<body>
    <div class="container mt-5" id="loginSection">
        <h1 class="mb-4">Login</h1>
        <button id="loginButton" class="btn btn-primary">Login</button>
    </div>

    <div class="container mt-5" id="syncSection" style="display: none;">
        <h1 class="mb-4">Secure Sync</h1>
        <div class="mb-4" id="syncControls">
            <h3>Sync Options:</h3>
            <label for="syncInterval" class="form-label">Sync Interval:</label>
            <select class="form-select" id="syncInterval">
                <option value="5000">5 seconds</option>
                <option value="10000">10 seconds</option>
                <option value="30000">30 seconds</option>
                <option value="60000">1 minute</option>
                <option value="180000">3 minutes</option>
                <option value="300000">5 minutes</option>
                <option value="600000">10 minutes</option>
                <option value="1800000">30 minutes</option>
                <option value="3600000">1 hour</option>
            </select>
            <button id="startSync" class="btn btn-success mt-3">Start Sync</button>
            <button id="stopSync" class="btn btn-danger mt-3">Stop Sync</button>
        </div>
    </div>

    <script>
        const MAX_ATTEMPTS = 5;
        const LOCKOUT_TIME = 60 * 60 * 1000; // 1 hour in milliseconds

        let lockoutTimer = null;
        let syncInterval = null;

        function showSyncSection() {
            document.getElementById('loginSection').style.display = 'none';
            document.getElementById('syncSection').style.display = 'block';
        }

        document.getElementById('loginButton').addEventListener('click', () => {
            if (lockoutTimer) {
                alert('You are temporarily locked out. Please try again later.');
                return;
            }

            const username = prompt('Enter username:');
            const password = prompt('Enter password:');

            fetch('/login/validate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Login successful!');
                        showSyncSection();
                    } else {
                        if (data.locked) {
                            alert('Too many attempts! You are locked out for 1 hour.');
                            lockoutTimer = setTimeout(() => {
                                lockoutTimer = null;
                            }, LOCKOUT_TIME);
                        } else {
                            alert(`Invalid credentials. You have ${data.remainingAttempts} attempts left.`);
                        }
                    }
                })
                .catch(error => {
                    console.error('Login error:', error);
                    alert('An error occurred. Please try again later.');
                });
        });

        document.getElementById('startSync').addEventListener('click', () => {

            const interval = parseInt(document.getElementById('syncInterval').value);

            // Update the JSON file to mark sync as active
            fetch('/sync/update-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: 1 })
            }).then(() => {
                syncInterval = setInterval(() => {
                    // Check JSON file before syncing
                    fetch('/sync', { method: 'POST' })
                        .then(response => response.json())
                        .then(syncData => console.log('Sync response:', syncData))
                        .catch(syncError => console.error('Sync error:', syncError));
                    
                }, interval);

                alert('Sync started.');
            }).catch(error => {
                console.error('Error updating sync status:', error);
                alert('Failed to start sync.');
            });
        });

        document.getElementById('stopSync').addEventListener('click', () => {

            // Update the JSON file to mark sync as inactive
            fetch('/sync/update-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: 0 })
            }).then(() => {
                clearInterval(syncInterval);
                syncInterval = null;
                alert('Sync stopped.');
            }).catch(error => {
                console.error('Error updating sync status:', error);
                alert('Failed to stop sync.');
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
