<?php

if (!function_exists('hexToMaterialColor')) {
    function hexToMaterialColor($hex): string
    {
        $hex = ltrim($hex, '#');

        $materialColors = [
            '2196F3' => 'Colors.blue',
            '9C27B0' => 'Colors.purple',
            '009688' => 'Colors.teal',
            'F44336' => 'Colors.red',
            'FF9800' => 'Colors.orange',
            '4CAF50' => 'Colors.green',
            '3F51B5' => 'Colors.indigo',
            'E91E63' => 'Colors.pink',
            '03A9F4' => 'Colors.lightBlue',
            '8BC34A' => 'Colors.lightGreen',
            'FFC107' => 'Colors.amber',
            '673AB7' => 'Colors.deepPurple',
            '795548' => 'Colors.brown',
            'EF5350' => 'Colors.red[300]',
            'FF5722' => 'Colors.deepOrange',
            '77DD77' => 'Colors.pastel_green'
        ];

        return $materialColors[strtoupper($hex)] ?? 'Colors.grey';
    }
}
