<?php
require_once '../_dbconnect.php';
require_once '../_config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect non-admincsr users
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: /login.php?error=access_denied");
    exit;
}

$users = [];
$query = "SELECT username FROM `user` WHERE SFTP_Enabled=?";
$stmt = $conn->prepare($query);
$sftpEnabled = 1;
$stmt->bind_param("i", $sftpEnabled);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $username = $row['username'];
        $folder = "/var/www/html/userUploads/$username";
        $status = '';

        if (!file_exists($folder)) {
            if (mkdir($folder, 0755, true)) {
                // Set ownership to root
                chown($folder, 'root');
                // Set permissions to 755
                chmod($folder, 0755);
                $status = '✅ Folder created and configured';
            } else {
                $status = '❌ Failed to create folder';
            }
        } else {
            // Ensure correct permissions and ownership even if it exists
            chown($folder, 'root');
            chmod($folder, 0755);
            $status = '✔️ Folder already exists (ownership and permissions updated)';
        }

        $users[] = [
            'username' => $username,
            'folder' => $folder,
            'status' => $status
        ];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>SFTP Folder Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <h2 class="mb-4">SFTP Folder Creation Status</h2>

        <?php if (empty($users)): ?>
            <div class="alert alert-warning">No users found with SFTP enabled.</div>
        <?php else: ?>
            <table class="table table-bordered bg-white shadow">
                <thead class="table-light">
                    <tr>
                        <th>Username</th>
                        <th>Folder Path</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['folder']) ?></td>
                            <td><?= $user['status'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <a href="/admin/dashboard.php" class="btn btn-secondary mt-4">← Back to Dashboard</a>
    </div>
</body>
</html>
