<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Risk;
use App\Models\SheEvent;
use Illuminate\Support\Facades\DB;

class MonteCarloController extends Controller
{
    public function simulate(Request $request)
    {
        $iterations = $request->input('iterations', 10000);
        $distributionType = $request->input('distribution', 'triangular');
        
        // Mapping from user to translate 1-5 scales to currency
        $mapping = $request->input('mapping', [
            1 => 1000,
            2 => 10000,
            3 => 50000,
            4 => 250000,
            5 => 1000000
        ]);

        $riskQuery = Risk::whereIn('status', ['Open', 'In Progress']);
        $sheQuery = SheEvent::whereNotIn('status', ['Completed', 'Closed']);

        // Apply filters if provided
        if ($dept = $request->input('department')) {
            $riskQuery->where('department_id', $dept);
            $sheQuery->where('department', $dept);
        }

        if ($cat = $request->input('category')) {
            $riskQuery->where('category', $cat);
            $sheQuery->where('activity_category', $cat);
        }

        $risks = $riskQuery->get();
        $sheEvents = $sheQuery->get();

        $startTime = microtime(true);
        $results = [];

        for ($i = 0; $i < $iterations; $i++) {
            $totalLoss = 0;

            // Simulate Risks
            foreach ($risks as $risk) {
                $likelihood = (float)$risk->residual_likelihood / 5.0; // Simple mapping: 5/5 = 100%
                if ($this->hit($likelihood)) {
                    $baseImpact = $mapping[$risk->residual_consequence] ?? 0;
                    $totalLoss += $this->sample($distributionType, $baseImpact);
                }
            }

            // Simulate SHE Events (Priority-based)
            foreach ($sheEvents as $event) {
                $priorityMapping = ['High' => 0.8, 'Medium' => 0.4, 'Low' => 0.1];
                $likelihood = $priorityMapping[$event->priority] ?? 0.2;
                if ($this->hit($likelihood)) {
                    $impactMapping = [
                        'High' => $mapping[5] ?? 500000, 
                        'Medium' => $mapping[3] ?? 50000, 
                        'Low' => $mapping[1] ?? 5000
                    ];
                    $baseImpact = $impactMapping[$event->priority] ?? ($mapping[2] ?? 10000);
                    $totalLoss += $this->sample($distributionType, $baseImpact);
                }
            }

            $results[] = $totalLoss;
        }

        sort($results);

        $executionTime = microtime(true) - $startTime;
        $mean = array_sum($results) / $iterations;
        $var95 = $results[(int)($iterations * 0.95)];
        $p90 = $results[(int)($iterations * 0.90)];
        $p95 = $results[(int)($iterations * 0.95)];

        // Generate distribution data for histogram (50 bins)
        $min = $results[0];
        $max = $results[$iterations - 1];
        $range = ($max - $min) ?: 1;
        $binSize = $range / 50;
        $histogram = array_fill(0, 50, 0);

        foreach ($results as $res) {
            $bin = min(49, (int)(($res - $min) / $binSize));
            $histogram[$bin]++;
        }

        $chartData = [];
        for ($j = 0; $j < 50; $j++) {
            $chartData[] = [
                'bin' => round($min + ($j * $binSize), 0),
                'count' => $histogram[$j]
            ];
        }

        return response()->json([
            'metrics' => [
                'mean' => round($mean, 2),
                'var95' => $var95,
                'p90' => $p90,
                'p95' => $p95,
                'min' => $min,
                'max' => $max,
                'iterations' => $iterations,
                'execution_time' => round($executionTime, 4),
                'risk_count' => $risks->count(),
                'she_count' => $sheEvents->count()
            ],
            'chartData' => $chartData,
            'sources' => [
                'risks' => $risks->map(fn($r) => ['id' => $r->sn, 'description' => $r->risk_description]),
                'she' => $sheEvents->map(fn($e) => ['id' => $e->action_id, 'description' => $e->activity_category])
            ]
        ]);
    }

    private function hit($probability)
    {
        return (mt_rand() / mt_getrandmax()) <= $probability;
    }

    private function sample($type, $base)
    {
        switch ($type) {
            case 'normal':
                // Box-Muller transform for normal distribution
                // Mean = $base, SD = $base * 0.2
                $u1 = mt_rand() / mt_getrandmax();
                $u2 = mt_rand() / mt_getrandmax();
                $z = sqrt(-2.0 * log($u1 ?: 0.0001)) * cos(2.0 * M_PI * $u2);
                return max(0, $base + ($z * $base * 0.2));

            case 'uniform':
                // Uniform: +/- 50% around base
                return mt_rand($base * 50, $base * 150) / 100;

            case 'triangular':
            default:
                // Triangular: min=base*0.5, max=base*2.0, likely=base
                $a = $base * 0.5;
                $b = $base * 2.0;
                $c = $base;
                $u = mt_rand() / mt_getrandmax();
                if ($u < ($c - $a) / ($b - $a)) {
                    return $a + sqrt($u * ($b - $a) * ($c - $a));
                } else {
                    return $b - sqrt((1 - $u) * ($b - $a) * ($b - $c));
                }
        }
    }
}
