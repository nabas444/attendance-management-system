<?php
/*
 * File    : includes/footer.php
 * Role    : Shared HTML footer and JS includes
 * Requires: Included by every page after main content
 */

require_once __DIR__ . '/auth.php';
$user = current_user();

// Rebuild base path
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']);
$basePath   = str_replace('\\', '/', realpath(__DIR__ . '/..') . '/');
$depth      = substr_count(str_replace($basePath, '', $scriptPath), '/');
$base       = str_repeat('../', $depth);
?>

<?php if ($user): ?>
  </main><!-- /.content-area -->
</div><!-- /.main-wrapper -->
<?php endif; ?>

<!-- Bootstrap 5 JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js 4 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<!-- Custom scripts -->
<script src="<?= $base ?>assets/js/main.js"></script>
<script src="<?= $base ?>assets/js/charts.js"></script>

</body>
</html>