</main>

</div>


<script>
  function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const overlay = document.getElementById('sidebarOverlay');

    if (window.innerWidth >= 1024) { // lg breakpoint
      // Desktop: toggle class sidebar-collapsed on sidebar, and content-expanded on mainContent
      sidebar.classList.toggle('sidebar-collapsed');
      if (mainContent) {
        mainContent.classList.toggle('content-expanded');
      }
    } else {
      // Mobile: toggle class -translate-x-full on sidebar, and hidden on overlay
      sidebar.classList.toggle('-translate-x-full');
      if (overlay) {
        overlay.classList.toggle('hidden');
      }
    }
  }

  function toggleUserDropdown(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('userDropdown');
    if (dropdown) {
      dropdown.classList.toggle('hidden');
    }
  }

  // Close dropdown when clicking outside
  window.addEventListener('click', function (e) {
    const dropdown = document.getElementById('userDropdown');
    if (dropdown && !dropdown.classList.contains('hidden')) {
      dropdown.classList.add('hidden');
    }
  });

  function filterMenu() {
    const input = document.getElementById('menuSearch');
    const filter = input.value.toUpperCase();
    const sections = document.querySelectorAll('.menu-section');

    sections.forEach(section => {
      let hasVisibleItem = false;
      const items = section.querySelectorAll('.menu-item');

      items.forEach(item => {
        const textElement = item.querySelector('.menu-text');
        if (textElement) {
          const textValue = textElement.textContent || textElement.innerText;
          if (textValue.toUpperCase().indexOf(filter) > -1) {
            item.style.display = "";
            hasVisibleItem = true;
          } else {
            item.style.display = "none";
          }
        }
      });

      // Hide the section title if no items match
      const title = section.querySelector('.menu-title');
      if (title) {
        if (hasVisibleItem) {
          title.style.display = "";
        } else {
          title.style.display = "none";
        }
      }
    });
  }
</script>
<footer class="bg-white  text-center border-t py-4  bottom-0 left-0 right-0 z-10 flex items-center justify-center mt-3">
  <p class="text-sm text-gray-600 ">
    &copy; <?= date('Y') ?> Remunerasi - PIT - RSUD Merauke. All rights reserved.
  </p>
</footer>
<?= $extraFooter ?>
</body>

</html>