<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';

$schema = (string) file_get_contents(__DIR__ . '/academic_items.sql');
$pdo->exec($schema);

if ((int) $pdo->query('SELECT COUNT(*) FROM academic_items')->fetchColumn() > 0) {
    echo "academic_items already contains data; import skipped.\n";
    exit(0);
}

$sourcePath = __DIR__ . '/../information/data/academic.json';
$source = json_decode((string) file_get_contents($sourcePath), true, 512, JSON_THROW_ON_ERROR);
$currentYear = (string) ($source['academicYear'] ?? '');
if (!preg_match('/^\d{4}$/', $currentYear)) {
    throw new RuntimeException('Invalid academic year in the existing data source.');
}

$insert = $pdo->prepare(
    "INSERT INTO academic_items (title, category, academic_year, details, status, display_order)
     VALUES (:title, :category, :year, :details, 'published', :display_order)"
);
$add = static function (string $title, string $category, string $year, array $details, int $order) use ($insert): void {
    $insert->execute([
        ':title' => $title,
        ':category' => $category,
        ':year' => $year,
        ':details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ':display_order' => $order,
    ]);
};

$pdo->beginTransaction();
try {
    foreach ((array) ($source['curriculum'] ?? []) as $order => $item) $add((string) $item['name'], 'curriculum', $currentYear, ['level'=>$item['level'] ?? '', 'description'=>$item['summary'] ?? ''], $order);
    foreach ((array) ($source['learningAreas'] ?? []) as $order => $title) $add((string) $title, 'learning_area', $currentYear, [], $order);
    foreach ((array) ($source['curriculumActivities'] ?? []) as $order => $title) $add((string) $title, 'curriculum_activity', $currentYear, [], $order);
    foreach ((array) ($source['subjectsByLevel'] ?? []) as $order => $item) $add((string) ($item['level'] ?? ''), 'subject_by_level', $currentYear, ['level'=>$item['level'] ?? '', 'items'=>$item['items'] ?? []], $order);
    foreach (['achievementPrimary'=>'achievement_primary', 'achievementSecondary'=>'achievement_secondary'] as $sourceKey => $category) {
        foreach ((array) ($source[$sourceKey] ?? []) as $order => $item) $add((string) ($item['grade'] ?? ''), $category, $currentYear, ['level'=>$item['grade'] ?? '', 'value'=>$item['average'] ?? 0], $order);
    }
    $subjectLabels = [];
    foreach ((array) ($source['onetSubjects'] ?? []) as $subject) $subjectLabels[(string) $subject['key']] = (string) $subject['label'];
    $order = 0;
    foreach ((array) ($source['onetByLevel'] ?? []) as $levelKey => $level) {
        foreach ((array) ($level['years'] ?? []) as $year => $scores) {
            foreach ((array) $scores as $subjectKey => $score) {
                $add($subjectLabels[$subjectKey] ?? (string) $subjectKey, 'onet', (string) $year, ['level_key'=>$levelKey, 'level'=>$level['label'] ?? $levelKey, 'short_label'=>$level['shortLabel'] ?? '', 'key'=>$subjectKey, 'value'=>$score], $order++);
            }
        }
    }
    $domainLabels = [];
    foreach ((array) ($source['ntDomains'] ?? []) as $domain) $domainLabels[(string) $domain['key']] = (string) $domain['label'];
    $order = 0;
    foreach ((array) ($source['ntTrend'] ?? []) as $year => $scores) {
        foreach ((array) $scores as $key => $score) $add($domainLabels[$key] ?? (string) $key, 'nt', (string) $year, ['scope'=>'trend', 'key'=>$key, 'value'=>$score], $order++);
    }
    foreach ((array) ($source['ntCompare2568'] ?? []) as $entityKey => $entity) {
        foreach (['thai', 'math', 'average'] as $key) $add($domainLabels[$key] ?? $key, 'nt', '2568', ['scope'=>'comparison', 'entity_key'=>$entityKey, 'level'=>$entity['label'] ?? $entityKey, 'key'=>$key, 'value'=>$entity[$key] ?? 0], $order++);
    }
    foreach ((array) ($source['graduation'] ?? []) as $order => $item) $add((string) ($item['level'] ?? ''), 'graduation', $currentYear, ['level'=>$item['level'] ?? '', 'value'=>$item['graduates'] ?? 0, 'value2'=>$item['percent'] ?? 0], $order);
    $pdo->commit();
    echo "Imported existing academic display data into academic_items.\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}
