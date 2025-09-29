  </main>
</div>

<script src="../kurdish-ui.js?v=2"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Select the button, sidebar, and main content area
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const content = document.querySelector('.content');

    // Check if the toggle and sidebar exist before adding listeners
    if (mobileMenuToggle && sidebar) {
      // Add a click event listener to the button
      mobileMenuToggle.addEventListener('click', function(event) {
        // Stop the click from propagating to the content area
        event.stopPropagation();
        // Toggle the .active class on the sidebar
        sidebar.classList.toggle('active');
      });

      // Optional: Close sidebar when clicking on the content area
      // This improves user experience on mobile
      if (content) {
        content.addEventListener('click', function() {
          // If the sidebar is active, remove the class to hide it
          if (sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
          }
        });
      }
    }
  });
</script>
</body>
</html>
