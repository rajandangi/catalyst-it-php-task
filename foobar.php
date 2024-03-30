<?php
function lazyFooBarGenerator($start, $end)
{
    for ($i = $start; $i <= $end; $i++) {
        $output = '';
        // divisible by three (3) output the word “foo”
        if ($i % 3 === 0) {
            $output .= 'foo';
        }
        // divisible by five (5) output the word “bar”
        if ($i % 5 === 0) {
            $output .= 'bar';
        }
        // divisible by three (3) and (5) output the word “foobar”
        yield $output ?: $i;
        if ($i != $end) {
            echo ', ';
        }
    }
}

// Generate a sequence in a memory-efficient way for scalability with large numbers.
foreach (lazyFooBarGenerator(1, 100) as $value) {
    echo $value;
}
echo PHP_EOL;