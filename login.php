<?php include 'includes/header.php'; ?>

<script>
    function show_hide_password(target) {
        var input = document.getElementById('password_hash');
        if (input.getAttribute('type') == 'password') {
            target.classList.add('view');
            input.setAttribute('type', 'text');
        } else {
            target.classList.remove('view');
            input.setAttribute('type', 'password');
        }
        return false;
    }
</script>
<h1 class="title">Сначала авторизируйся</h1>

<?php flash() ?>

<form method="post" action="do_login.php">
    <div>
        <label for="login">Username</label>
        <input list="codes" type="text" id="login" name="login" required>
    </div>
    <div>
        <label for="password_hash">Password</label>
        <input list="codes" type="password" id="password_hash" name="password_hash" required>
        <a href="#" class="password-control" onclick="return show_hide_password(this);"></a>
    </div>
    <div class="form-container">
        <a href="index.php"><button class="registration-button" type="submit">Login</button></a>
    </div>
</form>

<?php include 'includes/footer.php'; ?>