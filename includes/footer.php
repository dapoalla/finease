</main>
    
    <footer class="footer">
        <p><?php echo date('Y'); ?> FinEase (c) Software. All rights reserved.</p>
    </footer>
    
    <script src="<?php echo strpos($_SERVER['REQUEST_URI'], '/pages/') !== false ? '../assets/js/main.js' : 'assets/js/main.js'; ?>"></script>
</body>
</html>