<?php
require_once 'functions.php';
include 'header.php';
?>
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h3 class="mb-3">Register</h3>
            <?php if ($msg): ?>
                <div class="alert alert-warning"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>
            <form method="post" action="save_register.php">
                <div class="mb-3">
                    <label class="form-label">Full name</label>
                    <input type="text" name="fullname" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-">

                </div>
                <button type="submit" class="btn btn-primary">Register</button>
                <a href="login.php" class="btn btn-link">Login</a>
            </form>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>