</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const toast = document.getElementById('toast-notification');

  if (toast) {
    setTimeout(() => {
      toast.style.transition = 'opacity .25s ease, transform .25s ease';
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(-50%) translateY(-10px)';
    }, 2200);

    setTimeout(() => {
      toast.remove();
    }, 2500);
  }
});
</script>

</body>
</html>