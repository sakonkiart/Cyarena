<?php
/**
 * render_table — เวอร์ชันสวยขึ้น: responsive + striped + hover + compact
 */
function render_table(array $rows, array $columns, string $emptyMsg = 'ไม่พบข้อมูล') {
    if (empty($rows)) {
        echo '<div class="alert alert-light border d-flex align-items-center" role="alert" style="border-radius:12px">';
        echo '<div class="me-2">ℹ️</div><div>'.$emptyMsg.'</div></div>';
        return;
    }
    echo '<div class="table-responsive">';
    echo '<table class="table table-sm table-striped align-middle">';
    echo '<thead><tr>';
    foreach ($columns as $key => $label) echo '<th scope="col">'.htmlspecialchars($label).'</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
        echo '<tr>';
        foreach ($columns as $key => $label) {
            $val = $r[$key] ?? '';
            echo '<td>'.htmlspecialchars((string)$val).'</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}
