<?php

/**
 * Fails when the Clover report covers fewer statements, methods or, when the
 * report carries branch data, conditionals than the required percentage. Usage: coverage-check.php <clover.xml> <min>
 */

declare(strict_types=1);

$cloverPath = $argv[1] ?? null;
$minimum = isset($argv[2]) ? (float) $argv[2] : 100.0;

if ($cloverPath === null || ! is_file($cloverPath)) {
    fwrite(STDERR, "Usage: coverage-check.php <clover.xml> [minimum percentage]\n");
    exit(2);
}

$report = simplexml_load_file($cloverPath);
$metrics = $report === false ? null : $report->project?->metrics;

if ($metrics === null) {
    fwrite(STDERR, "The Clover report has no project metrics.\n");
    exit(2);
}

$failed = false;

foreach (
    [
    'statements' => 'coveredstatements',
    'methods' => 'coveredmethods',
    'conditionals' => 'coveredconditionals',
    ] as $total => $covered
) {
    $totalCount = (int) $metrics[$total];

    if ($totalCount === 0) {
        continue;
    }

    $percentage = (int) $metrics[$covered] / $totalCount * 100;
    $failed = $failed || $percentage < $minimum;

    printf(
        "%-13s %6.2f%% (%d/%d)%s\n",
        $total,
        $percentage,
        (int) $metrics[$covered],
        $totalCount,
        $percentage < $minimum ? '  below minimum' : '',
    );
}

exit($failed ? 1 : 0);
