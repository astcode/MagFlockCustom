<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'MagFlock Dashboard' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .header h1 {
            color: #667eea;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
            font-size: 1.1em;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card h3 {
            color: #667eea;
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 2.5em;
            font-weight: bold;
            color: #333;
        }
        .stat-card .label {
            color: #999;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .components {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .components h2 {
            color: #667eea;
            margin-bottom: 20px;
        }
        .component-list {
            display: grid;
            gap: 15px;
        }
        .component {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        .component-name {
            font-weight: bold;
            color: #333;
        }
        .component-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .status-running {
            background: #d4edda;
            color: #155724;
        }
        .status-stopped {
            background: #f8d7da;
            color: #721c24;
        }
        .status-loading {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔥 {{ $title ?? 'MagFlock Dashboard' }}</h1>
            <p>{{ $subtitle ?? 'MoBoMini Kernel - System Status' }}</p>
        </div>

        <div class="stats">
            @if(isset($stats))
                @foreach($stats as $stat)
                    <div class="stat-card">
                        <h3>{{ $stat['title'] }}</h3>
                        <div class="value">{{ $stat['value'] }}</div>
                        <div class="label">{{ $stat['label'] }}</div>
                    </div>
                @endforeach
            @endif
        </div>

        <div class="components">
            <h2>Components</h2>
            <div class="component-list">
                @if(isset($components) && count($components) > 0)
                    @foreach($components as $component)
                        <div class="component">
                            <div>
                                <div class="component-name">{{ $component['name'] }}</div>
                                <small style="color: #999;">v{{ $component['version'] }}</small>
                            </div>
                            <span class="component-status status-{{ $component['state'] }}">
                                {{ strtoupper($component['state']) }}
                            </span>
                        </div>
                    @endforeach
                @else
                    <div class="component">
                        <div class="component-name">No components loaded</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>