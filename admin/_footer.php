    </main>
  </div>
  <!-- const T 已在 _layout.php <head> 输出,这里不再重复 -->
  <script>window.GE_UPLOAD_HINTS = <?= json_encode(uploadHintsJson(), JSON_UNESCAPED_UNICODE) ?>;</script>
  <script src="admin-uploads.js" defer></script>
</body>
</html>
