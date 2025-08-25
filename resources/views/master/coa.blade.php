<!DOCTYPE html>
<html>
<head>
    <title>Export COA</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
        }
        .line {
            margin-bottom: 4px;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
<h3>Daftar COA</h3>
<div>
    @foreach($coaList as $line)
        <div class="line">{!! $line !!}</div>
    @endforeach
</div>
</body>
</html>
