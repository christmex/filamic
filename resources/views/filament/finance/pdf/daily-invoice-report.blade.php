<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Daily Invoice Report</title>
    <style>
        /* @font-face {
        font-family: SourceSansPro;
        src: url(SourceSansPro-Regular.ttf);
        } */

        .clearfix:after {
            content: "";
            display: table;
            clear: both;
        }

        a {
            text-decoration: none;
        }

        body {
            position: relative;
            /* width: 21cm;  
        height: 29.7cm;  */
            margin: 0 auto;
            color: #000;
            background: #FFFFFF;
            font-family: 'Calibri', sans-serif;
            font-size: 14px;
            font-weight: bold;
            /* font-family: 'Gill Sans', 'Gill Sans MT', Calibri, 'Trebuchet MS', sans-serif; */
        }

        header {
            /* padding: 10px 0; */
            margin-bottom: 10px;
            border-bottom: 1px solid #AAAAAA;
        }

        #logo {
            float: left;
            /* margin-top: 8px; */
        }

        #logo img {
            height: 60px;
        }

        #company {
            float: right;
            text-align: left;
        }


        #details {
            margin-bottom: 10px;
        }

        #client {
            padding-left: 6px;
            border-left: 6px solid #0087C3;
            float: left;
        }

        #client .to {
            color: #000;
        }

        h2.name {
            font-size: 1.4em;
            /* font-weight: normal; */
            margin: 0;
        }

        #invoice {
            float: right;
            text-align: right;
        }

        #invoice h1 {
            color: #000;
            font-size: 14px;
            line-height: 1em;
            /* font-weight: normal; */
            margin: 0 0 10px 0;
        }

        #invoice .date {
            font-size: 1.1em;
            color: #000;
            font-weight: bold;
        }

        table.det {
            border: 1px solid black;
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            margin-bottom: 10px;

        }

        table.det th,
        table.det td {
            padding: 5px;
            border: 1px solid black;
            text-align: center;
            /* border-bottom: 1px solid #FFFFFF; */
        }

        table.det th {
            white-space: nowrap;
            /* font-weight: normal; */
        }

        table.det td {
            text-align: center;
        }

        table.det td h3 {
            color: #000;
            font-size: 12px;
            font-weight: bold;
            margin: 0 0 0.2em 0;
        }

        table.det .desc {
            text-align: left;
        }


        table.det tfoot td {
            /* padding: 10px 20px; */
            background: #FFFFFF;
            border-bottom: none;
            font-size: 12px;
            white-space: nowrap;
            /* border-top: 1px solid #AAAAAA;  */
        }

        table.det tfoot tr:first-child td {
            border-top: none;
        }


        table.det tfoot tr td:first-child {
            border: none;
        }

        #thanks {
            font-size: 2em;
            margin-bottom: 50px;
        }

        #notices {
            padding-left: 6px;
            border-left: 6px solid #0087C3;
        }

        #notices .notice {
            font-size: 12px;
        }

        p {
            margin: 0
        }

        .no {
            text-align: center;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>
    <header class="clearfix">
        <div id="logo">
            <img src="{{ public_path('logo.png') }}">
            <h2 class="name">Sekolah Kristen Basic {{ $branch->name }}</h2>
            <p>{{ $branch->address }} <br /> {{ $branch->phone }}</p>
        </div>
    </header>
    <main>
        <table class="det" border="0" cellspacing="0" cellpadding="0">
            <thead>
                <tr>
                    <th class="no">#</th>
                    <th class="desc">Nama</th>
                    <th class="unit">Kelas</th>
                    @if($isMonthlyInvoice)
                        <th class="qty">Bulan</th>
                        <th class="qty">Denda</th>
                    @endif
                    <th class="qty">Nominal</th>
                    <th class="qty">Metode Pembayaran</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoices as $invoice)
                    <tr>
                        <td class="no">{{$loop->iteration}}</td>
                        <td class="desc">{{$invoice->student_name}}</td>
                        <td class="unit">{{$invoice->classroom_name}}</td>
                        @if($isMonthlyInvoice)
                            <td class="qty">{{$invoice->month?->getLabel()}}</td>
                            <td class="qty">{{ Number::currency($invoice->fine, 'IDR', 'id') }}</td>
                        @endif
                        <td class="qty">{{ Number::currency($invoice->amount, 'IDR', 'id') }}</td>
                        <td class="qty">{{$invoice->payment_method->getLabel()}}<br>({{$invoice->paid_at_app}})</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="page-break"></div>
        <table class="det" border="0" cellspacing="0" cellpadding="0">
            <thead>
                <tr>
                    <th class="qty">Rangkuman</th>
                    <th class="qty">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoices->groupBy('classroom_name') as $key => $invoice)
                    <tr>
                        <td>{{$key}}</td>
                        <td>{{$invoice->count()}} ({{ Number::currency($invoice->sum('amount'), 'IDR', 'id')}})</td>
                    </tr>
                @endforeach
                <tr>
                    <td>Total Pendapatan</td>
                    <td>{{ Number::currency($totalAmount, 'IDR', 'id') }}</td>
                </tr>
                @if($isMonthlyInvoice)
                    <tr>
                        <td>Total Denda</td>
                        <td>{{ Number::currency($totalFine, 'IDR', 'id') }}</td>
                    </tr>
                @endif
                <tr>
                    <td>Total Keseluruhan</td>
                    <td>{{ Number::currency($totalFine + $totalAmount, 'IDR', 'id') }}</td>
                </tr>
            </tbody>
        </table>
    </main>
</body>

</html>