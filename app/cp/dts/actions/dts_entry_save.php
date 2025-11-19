<?php
/**
 * DTS 条目保存动作（CP 基线）
 */

declare(strict_types=1);

require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

try {
    $entry_id = dts_post('entry_id');
    $dts_code = trim((string) dts_post('dts_code', ''));
    $entry_type = trim((string) dts_post('entry_type', 'custom'));
    $date_mode = trim((string) dts_post('date_mode', 'single'));
    $date_value = trim((string) dts_post('date_value', ''));
    $start_date = trim((string) dts_post('start_date', ''));
    $end_date = trim((string) dts_post('end_date', ''));
    $status = dts_post('status') ? 1 : 0;
    $show_to_front = dts_post('show_to_front') ? 1 : 0;
    $name_zh = trim((string) dts_post('name_zh', ''));
    $name_en = trim((string) dts_post('name_en', ''));
    $short_title = trim((string) dts_post('short_title', ''));
    $color_hex = trim((string) dts_post('color_hex', ''));
    $tag_class = trim((string) dts_post('tag_class', ''));
    $languages = trim((string) dts_post('languages', ''));
    $platforms = trim((string) dts_post('platforms', ''));
    $modules = trim((string) dts_post('modules', ''));
    $priority = (int) dts_post('priority', 100);
    $external_id = trim((string) dts_post('external_id', ''));
    $external_url = trim((string) dts_post('external_url', ''));
    $remark = trim((string) dts_post('remark', ''));

    if ($priority < 0) {
        $priority = 0;
    }

    if ($dts_code === '') {
        dts_set_feedback('danger', '请填写系统标识 code');
        header('Location: /cp/index.php?action=dts_entry_form' . ($entry_id ? "&id={$entry_id}" : ''));
        exit();
    }

    if ($name_zh === '') {
        dts_set_feedback('danger', '请填写主语言名称（中文）');
        header('Location: /cp/index.php?action=dts_entry_form' . ($entry_id ? "&id={$entry_id}" : ''));
        exit();
    }

    if (!in_array($date_mode, ['single', 'range'], true)) {
        dts_set_feedback('danger', '日期模式不合法');
        header('Location: /cp/index.php?action=dts_entry_form' . ($entry_id ? "&id={$entry_id}" : ''));
        exit();
    }

    if ($date_mode === 'single') {
        if ($date_value === '') {
            dts_set_feedback('danger', '请填写日期');
            header('Location: /cp/index.php?action=dts_entry_form' . ($entry_id ? "&id={$entry_id}" : ''));
            exit();
        }
        $start_date = null;
        $end_date = null;
    } else {
        if ($start_date === '' || $end_date === '') {
            dts_set_feedback('danger', '请填写起止日期');
            header('Location: /cp/index.php?action=dts_entry_form' . ($entry_id ? "&id={$entry_id}" : ''));
            exit();
        }

        if ($start_date > $end_date) {
            dts_set_feedback('danger', '起始日期不能晚于结束日期');
            header('Location: /cp/index.php?action=dts_entry_form' . ($entry_id ? "&id={$entry_id}" : ''));
            exit();
        }

        $date_value = null;
    }

    $unique_stmt = $pdo->prepare(
        'SELECT id FROM cp_dts_entry WHERE dts_code = ? AND source = "CP" AND id <> ? LIMIT 1'
    );
    $unique_stmt->execute([$dts_code, $entry_id ?: 0]);

    if ($unique_stmt->fetch()) {
        dts_set_feedback('danger', '该 code 已存在，请使用唯一的系统标识');
        header('Location: /cp/index.php?action=dts_entry_form' . ($entry_id ? "&id={$entry_id}" : ''));
        exit();
    }

    if ($entry_id) {
        $stmt = $pdo->prepare('
            UPDATE cp_dts_entry SET
                dts_code = :dts_code,
                entry_type = :entry_type,
                date_mode = :date_mode,
                date_value = :date_value,
                start_date = :start_date,
                end_date = :end_date,
                status = :status,
                show_to_front = :show_to_front,
                name_zh = :name_zh,
                name_en = :name_en,
                short_title = :short_title,
                color_hex = :color_hex,
                tag_class = :tag_class,
                languages = :languages,
                platforms = :platforms,
                modules = :modules,
                priority = :priority,
                external_id = :external_id,
                external_url = :external_url,
                remark = :remark,
                updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            ':dts_code' => $dts_code,
            ':entry_type' => $entry_type,
            ':date_mode' => $date_mode,
            ':date_value' => $date_value ?: null,
            ':start_date' => $start_date ?: null,
            ':end_date' => $end_date ?: null,
            ':status' => $status,
            ':show_to_front' => $show_to_front,
            ':name_zh' => $name_zh,
            ':name_en' => $name_en ?: null,
            ':short_title' => $short_title ?: null,
            ':color_hex' => $color_hex ?: null,
            ':tag_class' => $tag_class ?: null,
            ':languages' => $languages ?: null,
            ':platforms' => $platforms ?: null,
            ':modules' => $modules ?: null,
            ':priority' => $priority,
            ':external_id' => $external_id ?: null,
            ':external_url' => $external_url ?: null,
            ':remark' => $remark ?: null,
            ':id' => $entry_id,
        ]);

        dts_set_feedback('success', '条目更新成功');
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO cp_dts_entry (
                dts_code, entry_type, date_mode, date_value, start_date, end_date,
                status, show_to_front, name_zh, name_en, short_title, color_hex,
                tag_class, languages, platforms, modules, priority, external_id,
                external_url, remark, source, som_id, local_override, created_at, updated_at
            ) VALUES (
                :dts_code, :entry_type, :date_mode, :date_value, :start_date, :end_date,
                :status, :show_to_front, :name_zh, :name_en, :short_title, :color_hex,
                :tag_class, :languages, :platforms, :modules, :priority, :external_id,
                :external_url, :remark, "CP", NULL, 0, NOW(), NOW()
            )
        ');
        $stmt->execute([
            ':dts_code' => $dts_code,
            ':entry_type' => $entry_type,
            ':date_mode' => $date_mode,
            ':date_value' => $date_value ?: null,
            ':start_date' => $start_date ?: null,
            ':end_date' => $end_date ?: null,
            ':status' => $status,
            ':show_to_front' => $show_to_front,
            ':name_zh' => $name_zh,
            ':name_en' => $name_en ?: null,
            ':short_title' => $short_title ?: null,
            ':color_hex' => $color_hex ?: null,
            ':tag_class' => $tag_class ?: null,
            ':languages' => $languages ?: null,
            ':platforms' => $platforms ?: null,
            ':modules' => $modules ?: null,
            ':priority' => $priority,
            ':external_id' => $external_id ?: null,
            ':external_url' => $external_url ?: null,
            ':remark' => $remark ?: null,
        ]);

        dts_set_feedback('success', '条目创建成功');
    }

    header('Location: /cp/index.php?action=dts_entry');
    exit();
} catch (PDOException $e) {
    error_log('DTS Entry Save Error: ' . $e->getMessage());
    dts_set_feedback('danger', '保存失败：数据库错误');
    header('Location: /cp/index.php?action=dts_entry_form' . ($entry_id ? "&id={$entry_id}" : ''));
    exit();
} catch (Exception $e) {
    error_log('DTS Entry Save Error: ' . $e->getMessage());
    dts_set_feedback('danger', '保存失败：' . $e->getMessage());
    header('Location: /cp/index.php?action=dts_entry_form' . ($entry_id ? "&id={$entry_id}" : ''));
    exit();
}
