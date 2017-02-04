<?php

    require 'Bank.php';

    $bank       = new Bank();
    $categories = $bank->getCategories();

    $from = '2014-01';
    $to   = date('Y-m');

    $all_months = [];
    while($from <= $to)
    {
        $all_months[$from] = 0;
        $expenses = $bank->getExpenses($from);
        foreach($expenses as $expense)
        {
            $all_months[$from] += $expense['value'];
        }
        $from = date('Y-m', strtotime('+1 month', strtotime($from)));
    }
    if (!empty($_GET['month']) && preg_match('#^[0-9]{4}-[0-9]{2}$#', $_GET['month']))
    {
        $month_summary = [];
        $month_expenses = $bank->getExpenses($_GET['month']);
        foreach($month_expenses as $expense)
        {
            if (!isset($month_summary[$expense['category']]))
            {
                $month_summary[$expense['category']] = 0;
            }
            $month_summary[$expense['category']] += $expense['value'];
        }
    }

?>
<meta charset="utf-8">
<style>
    body
    {
        background: #ffffff;
    }
    #month
    {
        position: absolute;
        top: 0;
        left: 0;
        width: 30%;
        height: 100%;
    }
    #total
    {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
    #total-with-month
    {
        position: absolute;
        top: 0;
        left: 30%;
        width: 70%;
        height: 100%;
    }
</style>
<?php if (isset($month_summary)) : ?>
    <div id="month">
        <canvas id="js-month"></canvas>
    </div>
<?php endif; ?>
<div id="<?php echo isset($month_summary) ? 'total-with-month' : 'total'; ?>">
    <canvas id="js-total"></canvas>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.4.0/Chart.bundle.min.js"></script>
<script>
    <?php $color = 'rgb(' . rand(0, 255) .',' . rand(0, 255) .',' . rand(0, 255) .')'; ?>
    const totalCanvas = document.getElementById('js-total')
    const totalChart = new Chart(totalCanvas, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_keys($all_months)); ?>,
            datasets: [
                {
                    label: "All expenses",
                    fill: false,
                    lineTension: 0.1,
                    backgroundColor: "<?php echo $color; ?>",
                    borderColor: "<?php echo $color; ?>",
                    borderCapStyle: 'butt',
                    borderDash: [],
                    borderDashOffset: 0.0,
                    borderJoinStyle: 'miter',
                    pointBorderColor: "<?php echo $color; ?>",
                    pointBackgroundColor: "#fff",
                    pointBorderWidth: 1,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: "<?php echo $color; ?>",
                    pointHoverBorderColor: "rgba(220,220,220,1)",
                    pointHoverBorderWidth: 2,
                    pointRadius: 1,
                    pointHitRadius: 10,
                    data: <?php echo json_encode(array_map('abs', array_values($all_months))); ?>,
                    spanGaps: false,
                },
            ],
        },
        options: {
            responsive: true,
        }
    })
    totalCanvas.addEventListener('click', (evt) => {
        const points = totalChart.getElementsAtEvent(evt)
        const months = <?php echo json_encode(array_keys($all_months)); ?>;
        if (points[0])
        {
            document.location.href = '?month=' + months[points[0]._index]
        }
    })
</script>
<?php if (isset($month_summary)) : ?>
    <script>
        const monthCanvas = document.getElementById('js-month')
        const monthChart = new Chart(monthCanvas, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($month_summary)); ?>,
                datasets: [
                    {
                        label: "Monthly expenses (<?php echo $_GET['month']; ?>)",
                        fill: false,
                        lineTension: 0.1,
                        backgroundColor: [
                        <?php foreach($month_summary as $category) : ?>
                            <?php echo '"rgb(' . rand(0, 255) .',' . rand(0, 255) .',' . rand(0, 255) .')"'; ?>,
                        <?php endforeach; ?>
                        ],
                        // borderColor: "<?php echo $color; ?>",
                        borderCapStyle: 'butt',
                        borderDash: [],
                        borderWidth: 0,
                        borderDashOffset: 0.0,
                        borderJoinStyle: 'miter',
                        pointBorderColor: "<?php echo $color; ?>",
                        pointBackgroundColor: "#fff",
                        pointBorderWidth: 1,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: "<?php echo $color; ?>",
                        pointHoverBorderColor: "rgba(220,220,220,1)",
                        pointHoverBorderWidth: 2,
                        pointRadius: 1,
                        pointHitRadius: 10,
                        data: <?php echo json_encode(array_map('abs', array_values($month_summary))); ?>,
                        spanGaps: false,
                    },
                ],
            },
            options: {
                responsive: true,
            }
        })
        totalCanvas.addEventListener('click', (evt) => {
            const points = totalChart.getElementsAtEvent(evt)
            const months = <?php echo json_encode(array_keys($all_months)); ?>;
            if (points[0])
            {
                document.location.href = '?month=' + months[points[0]._index]
            }
        })
    </script>
<?php endif; ?>
