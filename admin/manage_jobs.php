<?php
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';

Auth::requireAdmin();

$pdo = Database::connection();

// Ensure jobs table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS `jobs` (
    `job_id` INT AUTO_INCREMENT PRIMARY KEY,
    `job_title` VARCHAR(255) NOT NULL,
    `job_category` ENUM('internship', 'attachment', 'job') NOT NULL,
    `job_description` TEXT NOT NULL,
    `job_requirements` TEXT NOT NULL,
    `job_deadline` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$message = '';
$error = '';

// Handle Job Posting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_job'])) {
    $title = trim($_POST['job_title']);
    $category = $_POST['job_category'];
    $description = trim($_POST['job_description']);
    $requirements = trim($_POST['job_requirements']);
    $deadline = $_POST['job_deadline'];

    if ($title && $category && $description && $requirements && $deadline) {
        $stmt = $pdo->prepare("INSERT INTO jobs (job_title, job_category, job_description, job_requirements, job_deadline) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$title, $category, $description, $requirements, $deadline])) {
            $message = "Job posted successfully!";
        } else {
            $error = "Failed to post job.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

// Handle Job Deletion
if (isset($_GET['delete'])) {
    $jobId = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM jobs WHERE job_id = ?");
    if ($stmt->execute([$jobId])) {
        $message = "Job deleted successfully!";
    }
}

// Fetch all jobs
$jobs = $pdo->query("SELECT * FROM jobs ORDER BY job_deadline ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs | Admin</title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .admin-card { background: white; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 25px; margin-bottom: 30px; }
        .header-bar { background: var(--color-secondary); color: white; padding: 20px 0; margin-bottom: 40px; }
    </style>
</head>
<body>

<div class="header-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <h2 class="mb-0">Job Management</h2>
        <a href="reports.php" class="btn btn-outline-light btn-sm">Back to Dashboard</a>
    </div>
</div>

<div class="container">
    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-5">
            <div class="admin-card">
                <h4>Post New Job</h4>
                <hr>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Job Title</label>
                        <input type="text" name="job_title" class="form-control" placeholder="e.g. IT Assistant" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="job_category" class="form-select" required>
                            <option value="job">Job Opening</option>
                            <option value="internship">Internship</option>
                            <option value="attachment">Attachment</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Job Description</label>
                        <textarea name="job_description" class="form-control" rows="3" placeholder="What will the applicant do?" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Job Requirements</label>
                        <textarea name="job_requirements" class="form-control" rows="3" placeholder="List requirements (one per line)" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deadline</label>
                        <input type="date" name="job_deadline" class="form-control" required>
                    </div>
                    <button type="submit" name="post_job" class="btn btn-primary w-100">Post Job Opportunity</button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="admin-card">
                <h4>Existing Postings</h4>
                <hr>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Deadline</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td><?= htmlspecialchars($job['job_title']) ?></td>
                                    <td><span class="badge bg-secondary"><?= ucfirst($job['job_category']) ?></span></td>
                                    <td>
                                        <?php 
                                        $deadline = strtotime($job['job_deadline']);
                                        $isExpired = $deadline < time();
                                        ?>
                                        <span class="<?= $isExpired ? 'text-danger fw-bold' : '' ?>">
                                            <?= date('d M Y', $deadline) ?>
                                            <?= $isExpired ? ' (Expired)' : '' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?delete=<?= $job['job_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this posting?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($jobs)): ?>
                                <tr><td colspan="4" class="text-center">No jobs posted yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
