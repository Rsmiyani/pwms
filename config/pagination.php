<?php
/**
 * Pagination helper — renders pagination UI
 * @param int $currentPage  Current page number (1-based)
 * @param int $totalPages   Total number of pages
 * @param int $totalRecords Total number of records
 * @param int $perPage      Records per page
 * @param string $baseUrl   Base URL with existing query params (without &page=)
 */
function renderPagination($currentPage, $totalPages, $totalRecords, $perPage, $baseUrl) {
    if ($totalPages <= 1) return;
    $start = ($currentPage - 1) * $perPage + 1;
    $end = min($currentPage * $perPage, $totalRecords);
    $sep = strpos($baseUrl, '?') !== false ? '&' : '?';
    ?>
    <div class="pg-wrap">
        <div class="pg-info">
            Showing <strong><?php echo $start; ?>–<?php echo $end; ?></strong> of <strong><?php echo $totalRecords; ?></strong> records
        </div>
        <nav class="pg-nav">
            <?php if ($currentPage > 1): ?>
                <a href="<?php echo $baseUrl . $sep . 'page=' . ($currentPage - 1); ?>" class="pg-btn"><i class="fas fa-chevron-left"></i></a>
            <?php else: ?>
                <span class="pg-btn disabled"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>

            <?php
            $range = 2;
            for ($i = 1; $i <= $totalPages; $i++):
                if ($i == 1 || $i == $totalPages || ($i >= $currentPage - $range && $i <= $currentPage + $range)):
            ?>
                    <a href="<?php echo $baseUrl . $sep . 'page=' . $i; ?>" class="pg-btn <?php echo $i === $currentPage ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php
                elseif ($i == $currentPage - $range - 1 || $i == $currentPage + $range + 1):
            ?>
                    <span class="pg-dots">...</span>
            <?php
                endif;
            endfor;
            ?>

            <?php if ($currentPage < $totalPages): ?>
                <a href="<?php echo $baseUrl . $sep . 'page=' . ($currentPage + 1); ?>" class="pg-btn"><i class="fas fa-chevron-right"></i></a>
            <?php else: ?>
                <span class="pg-btn disabled"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </nav>
    </div>
    <?php
}

/**
 * Build current page URL without 'page' param for pagination links
 */
function getPaginationBaseUrl() {
    $params = $_GET;
    unset($params['page']);
    $qs = http_build_query($params);
    $path = basename($_SERVER['PHP_SELF']);
    return $qs ? $path . '?' . $qs : $path;
}
