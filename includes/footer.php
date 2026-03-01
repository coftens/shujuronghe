</div><!-- /page-content -->
    </div><!-- /main-content -->
</div><!-- /layout -->

<script>
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('userDropdown');
    const userInfo = document.querySelector('.user-info');
    if (dropdown && !userInfo.contains(e.target)) {
        dropdown.style.display = 'none';
    }
});
</script>
</body>
</html>
