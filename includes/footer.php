        </div><!-- /page-content -->
    </div><!-- /main-content -->
</div><!-- /layout -->

<script>
// 点击其他地方关闭下拉菜单
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
