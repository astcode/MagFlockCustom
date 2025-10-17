PS O:\phpLaragon\www\MagFlockCustom\magmobomini> php test-kernel.php
╔════════════════════════════════════════════════════════════╗
║           MoBoMini Kernel Test Suite                      ║
╚════════════════════════════════════════════════════════════╝

[TEST 1] Loading bootstrap...
[2025-10-06 20:24:21] [INFO] [KERNEL] Initializing MoBoMini Kernel
[2025-10-06 20:24:21] [DEBUG] [CONFIG] Config loaded: O:\phpLaragon\www\MagFlockCustom\magmobomini/config/mobo.php
[2025-10-06 20:24:21] [DEBUG] [STATE] State loaded
[2025-10-06 20:24:21] [INFO] [KERNEL] Kernel initialized
[2025-10-06 20:24:21] [INFO] [REGISTRY] Component registered: MagDB {"version":"1.0.0","dependencies":[]}
[2025-10-06 20:24:21] [DEBUG] [EVENTBUS] Event emitted: component.registered {"name":"MagDB"}
[2025-10-06 20:24:21] [INFO] [REGISTRY] Component registered: MagPuma {"version":"1.0.0","dependencies":["MagDB"]}
[2025-10-06 20:24:21] [DEBUG] [EVENTBUS] Event emitted: component.registered {"name":"MagPuma"}
[2025-10-06 20:24:21] [INFO] [REGISTRY] Component registered: MagGate {"version":"1.0.0","dependencies":["MagDB"]}
[2025-10-06 20:24:21] [DEBUG] [EVENTBUS] Event emitted: component.registered {"name":"MagGate"}
[2025-10-06 20:24:21] [INFO] [REGISTRY] Component registered: MagView {"version":"1.0.0","dependencies":[]}
[2025-10-06 20:24:21] [DEBUG] [EVENTBUS] Event emitted: component.registered {"name":"MagView"}
✓ Bootstrap loaded

[TEST 2] Checking kernel instance...
  - Name: MoBoMini
  - Version: 1.0.0
  - Booted: No
✓ Kernel instance OK

[TEST 3] Testing subsystems...
  - Config: MoBo\ConfigManager
  - Logger: MoBo\Logger
  - EventBus: MoBo\EventBus
  - Registry: MoBo\Registry
  - Health: MoBo\HealthMonitor
  - Lifecycle: MoBo\LifecycleManager
  - State: MoBo\StateManager
  - Cache: MoBo\CacheManager
✓ All subsystems initialized

[TEST 4] Testing EventBus...
[2025-10-06 20:24:21] [DEBUG] [EVENTBUS] Handler registered for event: test.event {"id":"handler_68e42575a50ae0.61412842","priority":50}
[2025-10-06 20:24:21] [DEBUG] [EVENTBUS] Event emitted: test.event {"message":"Hello from EventBus!"}
✓ EventBus working

[TEST 5] Testing Cache...
[2025-10-06 20:24:21] [DEBUG] [CACHE] Cache set: test_key {"ttl":60}
✓ Cache working

[TEST 6] Testing State...
[2025-10-06 20:24:21] [DEBUG] [STATE] State saved
✓ State working

[TEST 7] Testing Config...
✓ Config working

[TEST 8] Testing Logger...
[2025-10-06 20:24:21] [INFO] [TEST] Test log message
✓ Logger working (check storage/logs/mobo.log)

[TEST 9] Testing MagDS Database Connection...

❌ TEST FAILED!
Error: Trying to access array offset on null
File: O:\phpLaragon\www\MagFlockCustom\magmobomini\test-kernel.php:82

Stack Trace:
#0 O:\phpLaragon\www\MagFlockCustom\magmobomini\test-kernel.php(82): {closure:O:\phpLaragon\www\MagFlockCustom\magmobomini\bootstrap.php:11}(2, 'Trying to acces...', 'O:\\phpLaragon\\w...', 82)
#1 {main}
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini> php test-magdb.php
[2025-10-06 20:24:26] [INFO] [KERNEL] Initializing MoBoMini Kernel
[2025-10-06 20:24:26] [DEBUG] [CONFIG] Config loaded: O:\phpLaragon\www\MagFlockCustom\magmobomini/config/mobo.php
[2025-10-06 20:24:26] [DEBUG] [STATE] State loaded
[2025-10-06 20:24:26] [INFO] [KERNEL] Kernel initialized
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component registered: MagDB {"version":"1.0.0","dependencies":[]}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.registered {"name":"MagDB"}
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component registered: MagPuma {"version":"1.0.0","dependencies":["MagDB"]}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.registered {"name":"MagPuma"}
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component registered: MagGate {"version":"1.0.0","dependencies":["MagDB"]}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.registered {"name":"MagGate"}
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component registered: MagView {"version":"1.0.0","dependencies":[]}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.registered {"name":"MagView"}
╔════════════════════════════════════════════════════════════╗
║           MagDB Test Suite                                ║
╚════════════════════════════════════════════════════════════╝

[TEST 1] Loading kernel...
✓ Kernel loaded

[TEST 2] Checking MagDB registration...
✓ MagDB already registered in bootstrap
✓ Retrieved from registry

[TEST 3] Booting kernel...
[2025-10-06 20:24:26] [INFO] [BOOT] === MoBoMini Boot Sequence Started ===
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: system.boot
[2025-10-06 20:24:26] [INFO] [BOOT] [STAGE 1] PRE-BOOT
[2025-10-06 20:24:26] [INFO] [BOOT] ✓ Configuration validated
[2025-10-06 20:24:26] [INFO] [BOOT] ✓ File permissions verified
[2025-10-06 20:24:26] [INFO] [BOOT] [STAGE 2] KERNEL INIT
[2025-10-06 20:24:26] [INFO] [BOOT] ✓ Event bus initialized
[2025-10-06 20:24:26] [INFO] [BOOT] ✓ Registry initialized
[2025-10-06 20:24:26] [INFO] [BOOT] ✓ State manager initialized
[2025-10-06 20:24:26] [INFO] [BOOT] [STAGE 3] POST (Power-On Self-Test)
[2025-10-06 20:24:26] [INFO] [BOOT] ✓ Database config not found (skipping test)
[2025-10-06 20:24:26] [INFO] [REGISTRY] Dependencies resolved {"order":["MagDB","MagPuma","MagGate","MagView"]}
[2025-10-06 20:24:26] [INFO] [BOOT] ✓ Component dependencies resolved
[2025-10-06 20:24:26] [INFO] [BOOT] [STAGE 4] COMPONENT LOADING
[2025-10-06 20:24:26] [INFO] [BOOT] Loading component: MagDB
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagDB {"old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagDB","old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:26] [INFO] [BOOT] ✓ Component loaded: MagDB
[2025-10-06 20:24:26] [INFO] [BOOT] Loading component: MagPuma
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagPuma {"old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagPuma","old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:26] [INFO] [BOOT] ✓ Component loaded: MagPuma
[2025-10-06 20:24:26] [INFO] [BOOT] Loading component: MagGate
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagGate {"old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagGate","old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:26] [INFO] [BOOT] ✓ Component loaded: MagGate
[2025-10-06 20:24:26] [INFO] [BOOT] Loading component: MagView
[2025-10-06 20:24:26] [INFO] [MAGVIEW] MagView booted
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagView {"old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagView","old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:26] [INFO] [BOOT] ✓ Component loaded: MagView
[2025-10-06 20:24:26] [INFO] [BOOT] [STAGE 5] SERVICE START
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Starting all components
[2025-10-06 20:24:26] [INFO] [REGISTRY] Dependencies resolved {"order":["MagDB","MagPuma","MagGate","MagView"]}
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Starting component: MagDB
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagDB {"old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagDB","old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagDB {"old_state":"starting","new_state":"running"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagDB","old_state":"starting","new_state":"running"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.started {"name":"MagDB"}
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Component started: MagDB
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Starting component: MagPuma
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagPuma {"old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagPuma","old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagPuma {"old_state":"starting","new_state":"running"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagPuma","old_state":"starting","new_state":"running"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.started {"name":"MagPuma"}
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Component started: MagPuma
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Starting component: MagGate
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagGate {"old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagGate","old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagGate {"old_state":"starting","new_state":"running"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagGate","old_state":"starting","new_state":"running"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.started {"name":"MagGate"}
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Component started: MagGate
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Starting component: MagView
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagView {"old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagView","old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:26] [INFO] [MAGVIEW] MagView started
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagView {"old_state":"starting","new_state":"running"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagView","old_state":"starting","new_state":"running"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.started {"name":"MagView"}
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Component started: MagView
[2025-10-06 20:24:26] [INFO] [BOOT] ✓ All services started
[2025-10-06 20:24:26] [INFO] [BOOT] [STAGE 6] SYSTEM READY
[2025-10-06 20:24:26] [DEBUG] [STATE] State saved
[2025-10-06 20:24:26] [DEBUG] [STATE] State saved
[2025-10-06 20:24:26] [DEBUG] [STATE] State saved
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: system.ready

╔════════════════════════════════════════════════════════════╗
║              MoBoMini System Ready                         ║
╠════════════════════════════════════════════════════════════╣
║ Components Loaded: 4                                    ║
║  ✓ MagDB                                               ║
║  ✓ MagPuma                                             ║
║  ✓ MagGate                                             ║
║  ✓ MagView                                             ║
╠════════════════════════════════════════════════════════════╣
║ System: http://mobo.magflock.test                  ║
╚════════════════════════════════════════════════════════════╝

[2025-10-06 20:24:26] [INFO] [BOOT] === MoBoMini Boot Complete ===
✓ Kernel booted

[TEST 4] Testing database connections...

[TEST 5] Testing simple query...
  PostgreSQL Version: PostgreSQL 17.6 (Debian 17.6-2.pgdg11+1) on x86_64-pc-linux-gnu, compiled by gcc (Debian 10.2.1-6) 10.2.1 20210110, 64-bit
✓ Query executed

[TEST 6] Testing parameterized query...
  Result: Failed
✓ Parameterized query executed

[TEST 7] Testing fetchOne...
  Current Time: 2025-10-06 16:24:26.429377-04
✓ fetchOne executed

[TEST 8] Testing fetchColumn...
  Tables in public schema: 0
✓ fetchColumn executed

[TEST 9] Connection statistics...
  magui:
    Queries: 4
    Total Time: 0.0057s
    Avg Time: 0.0014s

╔════════════════════════════════════════════════════════════╗
║              ALL TESTS PASSED! 🔥                          ║
╚════════════════════════════════════════════════════════════╝

[2025-10-06 20:24:26] [INFO] [KERNEL] Kernel shutdown initiated
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Initiating graceful shutdown {"timeout":30}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: system.shutdown {"timeout":30}
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Stopping all components
[2025-10-06 20:24:26] [INFO] [REGISTRY] Dependencies resolved {"order":["MagDB","MagPuma","MagGate","MagView"]}
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Stopping component: MagView
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagView {"old_state":"running","new_state":"stopping"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagView","old_state":"running","new_state":"stopping"}
[2025-10-06 20:24:26] [INFO] [MAGVIEW] MagView stopped
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagView {"old_state":"stopping","new_state":"stopped"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagView","old_state":"stopping","new_state":"stopped"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.stopped {"name":"MagView"}
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Component stopped: MagView
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Stopping component: MagGate
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagGate {"old_state":"running","new_state":"stopping"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagGate","old_state":"running","new_state":"stopping"}
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagGate {"old_state":"stopping","new_state":"stopped"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagGate","old_state":"stopping","new_state":"stopped"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.stopped {"name":"MagGate"}
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Component stopped: MagGate
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Stopping component: MagPuma
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagPuma {"old_state":"running","new_state":"stopping"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagPuma","old_state":"running","new_state":"stopping"}
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagPuma {"old_state":"stopping","new_state":"stopped"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagPuma","old_state":"stopping","new_state":"stopped"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.stopped {"name":"MagPuma"}
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Component stopped: MagPuma
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Stopping component: MagDB
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagDB {"old_state":"running","new_state":"stopping"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagDB","old_state":"running","new_state":"stopping"}
[2025-10-06 20:24:26] [INFO] [REGISTRY] Component state changed: MagDB {"old_state":"stopping","new_state":"stopped"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagDB","old_state":"stopping","new_state":"stopped"}
[2025-10-06 20:24:26] [DEBUG] [EVENTBUS] Event emitted: component.stopped {"name":"MagDB"}
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Component stopped: MagDB
[2025-10-06 20:24:26] [INFO] [LIFECYCLE] Shutdown complete
[2025-10-06 20:24:26] [DEBUG] [STATE] State saved
[2025-10-06 20:24:26] [DEBUG] [STATE] State saved
[2025-10-06 20:24:26] [INFO] [KERNEL] Kernel shutdown complete
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini> php test-magview.php
╔════════════════════════════════════════════════════════════╗
║           MagView Test Suite                              ║
╚════════════════════════════════════════════════════════════╝

[TEST 1] Loading kernel...
[2025-10-06 20:24:35] [INFO] [KERNEL] Initializing MoBoMini Kernel
[2025-10-06 20:24:35] [DEBUG] [CONFIG] Config loaded: O:\phpLaragon\www\MagFlockCustom\magmobomini/config/mobo.php
[2025-10-06 20:24:35] [DEBUG] [STATE] State loaded
[2025-10-06 20:24:35] [INFO] [KERNEL] Kernel initialized
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component registered: MagDB {"version":"1.0.0","dependencies":[]}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.registered {"name":"MagDB"}
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component registered: MagPuma {"version":"1.0.0","dependencies":["MagDB"]}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.registered {"name":"MagPuma"}
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component registered: MagGate {"version":"1.0.0","dependencies":["MagDB"]}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.registered {"name":"MagGate"}
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component registered: MagView {"version":"1.0.0","dependencies":[]}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.registered {"name":"MagView"}
✓ Kernel loaded

[TEST 2] Registering MagView component...
✓ MagView already registered in bootstrap

✓ Retrieved from registry

✓ MagView registered

[TEST 3] Booting kernel...
[2025-10-06 20:24:35] [INFO] [BOOT] === MoBoMini Boot Sequence Started ===
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: system.boot
[2025-10-06 20:24:35] [INFO] [BOOT] [STAGE 1] PRE-BOOT
[2025-10-06 20:24:35] [INFO] [BOOT] ✓ Configuration validated
[2025-10-06 20:24:35] [INFO] [BOOT] ✓ File permissions verified
[2025-10-06 20:24:35] [INFO] [BOOT] [STAGE 2] KERNEL INIT
[2025-10-06 20:24:35] [INFO] [BOOT] ✓ Event bus initialized
[2025-10-06 20:24:35] [INFO] [BOOT] ✓ Registry initialized
[2025-10-06 20:24:35] [INFO] [BOOT] ✓ State manager initialized
[2025-10-06 20:24:35] [INFO] [BOOT] [STAGE 3] POST (Power-On Self-Test)
[2025-10-06 20:24:35] [INFO] [BOOT] ✓ Database config not found (skipping test)
[2025-10-06 20:24:35] [INFO] [REGISTRY] Dependencies resolved {"order":["MagDB","MagPuma","MagGate","MagView"]}
[2025-10-06 20:24:35] [INFO] [BOOT] ✓ Component dependencies resolved
[2025-10-06 20:24:35] [INFO] [BOOT] [STAGE 4] COMPONENT LOADING
[2025-10-06 20:24:35] [INFO] [BOOT] Loading component: MagDB
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagDB {"old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagDB","old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:35] [INFO] [BOOT] ✓ Component loaded: MagDB
[2025-10-06 20:24:35] [INFO] [BOOT] Loading component: MagPuma
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagPuma {"old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagPuma","old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:35] [INFO] [BOOT] ✓ Component loaded: MagPuma
[2025-10-06 20:24:35] [INFO] [BOOT] Loading component: MagGate
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagGate {"old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagGate","old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:35] [INFO] [BOOT] ✓ Component loaded: MagGate
[2025-10-06 20:24:35] [INFO] [BOOT] Loading component: MagView
[2025-10-06 20:24:35] [INFO] [MAGVIEW] MagView booted
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagView {"old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagView","old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:35] [INFO] [BOOT] ✓ Component loaded: MagView
[2025-10-06 20:24:35] [INFO] [BOOT] [STAGE 5] SERVICE START
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Starting all components
[2025-10-06 20:24:35] [INFO] [REGISTRY] Dependencies resolved {"order":["MagDB","MagPuma","MagGate","MagView"]}
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Starting component: MagDB
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagDB {"old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagDB","old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagDB {"old_state":"starting","new_state":"running"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagDB","old_state":"starting","new_state":"running"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.started {"name":"MagDB"}
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Component started: MagDB
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Starting component: MagPuma
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagPuma {"old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagPuma","old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagPuma {"old_state":"starting","new_state":"running"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagPuma","old_state":"starting","new_state":"running"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.started {"name":"MagPuma"}
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Component started: MagPuma
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Starting component: MagGate
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagGate {"old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagGate","old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagGate {"old_state":"starting","new_state":"running"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagGate","old_state":"starting","new_state":"running"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.started {"name":"MagGate"}
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Component started: MagGate
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Starting component: MagView
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagView {"old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagView","old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:35] [INFO] [MAGVIEW] MagView started
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagView {"old_state":"starting","new_state":"running"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagView","old_state":"starting","new_state":"running"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.started {"name":"MagView"}
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Component started: MagView
[2025-10-06 20:24:35] [INFO] [BOOT] ✓ All services started
[2025-10-06 20:24:35] [INFO] [BOOT] [STAGE 6] SYSTEM READY
[2025-10-06 20:24:35] [DEBUG] [STATE] State saved
[2025-10-06 20:24:35] [DEBUG] [STATE] State saved
[2025-10-06 20:24:35] [DEBUG] [STATE] State saved
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: system.ready

╔════════════════════════════════════════════════════════════╗
║              MoBoMini System Ready                         ║
╠════════════════════════════════════════════════════════════╣
║ Components Loaded: 4                                    ║
║  ✓ MagDB                                               ║
║  ✓ MagPuma                                             ║
║  ✓ MagGate                                             ║
║  ✓ MagView                                             ║
╠════════════════════════════════════════════════════════════╣
║ System: http://mobo.magflock.test                  ║
╚════════════════════════════════════════════════════════════╝

[2025-10-06 20:24:35] [INFO] [BOOT] === MoBoMini Boot Complete ===
✓ Kernel booted

[TEST 4] Rendering dashboard...
✓ Dashboard rendered
  → Saved to: dashboard.html

╔════════════════════════════════════════════════════════════╗
║              ALL TESTS PASSED! 🔥                          ║
╚════════════════════════════════════════════════════════════╝

Open dashboard.html in your browser to see the result!

[2025-10-06 20:24:35] [INFO] [KERNEL] Kernel shutdown initiated
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Initiating graceful shutdown {"timeout":30}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: system.shutdown {"timeout":30}
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Stopping all components
[2025-10-06 20:24:35] [INFO] [REGISTRY] Dependencies resolved {"order":["MagDB","MagPuma","MagGate","MagView"]}
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Stopping component: MagView
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagView {"old_state":"running","new_state":"stopping"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagView","old_state":"running","new_state":"stopping"}
[2025-10-06 20:24:35] [INFO] [MAGVIEW] MagView stopped
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagView {"old_state":"stopping","new_state":"stopped"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagView","old_state":"stopping","new_state":"stopped"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.stopped {"name":"MagView"}
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Component stopped: MagView
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Stopping component: MagGate
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagGate {"old_state":"running","new_state":"stopping"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagGate","old_state":"running","new_state":"stopping"}
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagGate {"old_state":"stopping","new_state":"stopped"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagGate","old_state":"stopping","new_state":"stopped"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.stopped {"name":"MagGate"}
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Component stopped: MagGate
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Stopping component: MagPuma
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagPuma {"old_state":"running","new_state":"stopping"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagPuma","old_state":"running","new_state":"stopping"}
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagPuma {"old_state":"stopping","new_state":"stopped"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagPuma","old_state":"stopping","new_state":"stopped"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.stopped {"name":"MagPuma"}
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Component stopped: MagPuma
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Stopping component: MagDB
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagDB {"old_state":"running","new_state":"stopping"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagDB","old_state":"running","new_state":"stopping"}
[2025-10-06 20:24:35] [INFO] [REGISTRY] Component state changed: MagDB {"old_state":"stopping","new_state":"stopped"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagDB","old_state":"stopping","new_state":"stopped"}
[2025-10-06 20:24:35] [DEBUG] [EVENTBUS] Event emitted: component.stopped {"name":"MagDB"}
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Component stopped: MagDB
[2025-10-06 20:24:35] [INFO] [LIFECYCLE] Shutdown complete
[2025-10-06 20:24:35] [DEBUG] [STATE] State saved
[2025-10-06 20:24:35] [DEBUG] [STATE] State saved
[2025-10-06 20:24:35] [INFO] [KERNEL] Kernel shutdown complete
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini>
PS O:\phpLaragon\www\MagFlockCustom\magmobomini> php test-maggate-magpuma.php
[2025-10-06 20:24:41] [INFO] [KERNEL] Initializing MoBoMini Kernel
[2025-10-06 20:24:41] [DEBUG] [CONFIG] Config loaded: O:\phpLaragon\www\MagFlockCustom\magmobomini/config/mobo.php
[2025-10-06 20:24:42] [DEBUG] [STATE] State loaded
[2025-10-06 20:24:42] [INFO] [KERNEL] Kernel initialized
[2025-10-06 20:24:42] [INFO] [REGISTRY] Component registered: MagDB {"version":"1.0.0","dependencies":[]}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.registered {"name":"MagDB"}
[2025-10-06 20:24:42] [INFO] [REGISTRY] Component registered: MagPuma {"version":"1.0.0","dependencies":["MagDB"]}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.registered {"name":"MagPuma"}
[2025-10-06 20:24:42] [INFO] [REGISTRY] Component registered: MagGate {"version":"1.0.0","dependencies":["MagDB"]}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.registered {"name":"MagGate"}
[2025-10-06 20:24:42] [INFO] [REGISTRY] Component registered: MagView {"version":"1.0.0","dependencies":[]}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.registered {"name":"MagView"}
🔥🔥🔥 TESTING MAGGATE + MAGPUMA 🐆🐆🐆

[2025-10-06 20:24:42] [INFO] [BOOT] === MoBoMini Boot Sequence Started ===
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: system.boot
[2025-10-06 20:24:42] [INFO] [BOOT] [STAGE 1] PRE-BOOT
[2025-10-06 20:24:42] [INFO] [BOOT] ✓ Configuration validated
[2025-10-06 20:24:42] [INFO] [BOOT] ✓ File permissions verified
[2025-10-06 20:24:42] [INFO] [BOOT] [STAGE 2] KERNEL INIT
[2025-10-06 20:24:42] [INFO] [BOOT] ✓ Event bus initialized
[2025-10-06 20:24:42] [INFO] [BOOT] ✓ Registry initialized
[2025-10-06 20:24:42] [INFO] [BOOT] ✓ State manager initialized
[2025-10-06 20:24:42] [INFO] [BOOT] [STAGE 3] POST (Power-On Self-Test)
[2025-10-06 20:24:42] [INFO] [BOOT] ✓ Database config not found (skipping test)
[2025-10-06 20:24:42] [INFO] [REGISTRY] Dependencies resolved {"order":["MagDB","MagPuma","MagGate","MagView"]}
[2025-10-06 20:24:42] [INFO] [BOOT] ✓ Component dependencies resolved
[2025-10-06 20:24:42] [INFO] [BOOT] [STAGE 4] COMPONENT LOADING
[2025-10-06 20:24:42] [INFO] [BOOT] Loading component: MagDB
[2025-10-06 20:24:42] [INFO] [REGISTRY] Component state changed: MagDB {"old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagDB","old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:42] [INFO] [BOOT] ✓ Component loaded: MagDB
[2025-10-06 20:24:42] [INFO] [BOOT] Loading component: MagPuma
[2025-10-06 20:24:42] [INFO] [REGISTRY] Component state changed: MagPuma {"old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagPuma","old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:42] [INFO] [BOOT] ✓ Component loaded: MagPuma
[2025-10-06 20:24:42] [INFO] [BOOT] Loading component: MagGate
[2025-10-06 20:24:42] [INFO] [REGISTRY] Component state changed: MagGate {"old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagGate","old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:42] [INFO] [BOOT] ✓ Component loaded: MagGate
[2025-10-06 20:24:42] [INFO] [BOOT] Loading component: MagView
[2025-10-06 20:24:42] [INFO] [MAGVIEW] MagView booted
[2025-10-06 20:24:42] [INFO] [REGISTRY] Component state changed: MagView {"old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagView","old_state":"registered","new_state":"loaded"}
[2025-10-06 20:24:42] [INFO] [BOOT] ✓ Component loaded: MagView
[2025-10-06 20:24:42] [INFO] [BOOT] [STAGE 5] SERVICE START
[2025-10-06 20:24:42] [INFO] [LIFECYCLE] Starting all components
[2025-10-06 20:24:42] [INFO] [REGISTRY] Dependencies resolved {"order":["MagDB","MagPuma","MagGate","MagView"]}
[2025-10-06 20:24:42] [INFO] [LIFECYCLE] Starting component: MagDB
[2025-10-06 20:24:42] [INFO] [REGISTRY] Component state changed: MagDB {"old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagDB","old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:42] [INFO] [REGISTRY] Component state changed: MagDB {"old_state":"starting","new_state":"running"}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagDB","old_state":"starting","new_state":"running"}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.started {"name":"MagDB"}
[2025-10-06 20:24:42] [INFO] [LIFECYCLE] Component started: MagDB
[2025-10-06 20:24:42] [INFO] [LIFECYCLE] Starting component: MagPuma
[2025-10-06 20:24:42] [INFO] [REGISTRY] Component state changed: MagPuma {"old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagPuma","old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:42] [INFO] [REGISTRY] Component state changed: MagPuma {"old_state":"starting","new_state":"running"}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagPuma","old_state":"starting","new_state":"running"}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.started {"name":"MagPuma"}
[2025-10-06 20:24:42] [INFO] [LIFECYCLE] Component started: MagPuma
[2025-10-06 20:24:42] [INFO] [LIFECYCLE] Starting component: MagGate
[2025-10-06 20:24:42] [INFO] [REGISTRY] Component state changed: MagGate {"old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagGate","old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:42] [INFO] [REGISTRY] Component state changed: MagGate {"old_state":"starting","new_state":"running"}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagGate","old_state":"starting","new_state":"running"}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.started {"name":"MagGate"}
[2025-10-06 20:24:42] [INFO] [LIFECYCLE] Component started: MagGate
[2025-10-06 20:24:42] [INFO] [LIFECYCLE] Starting component: MagView
[2025-10-06 20:24:42] [INFO] [REGISTRY] Component state changed: MagView {"old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagView","old_state":"loaded","new_state":"starting"}
[2025-10-06 20:24:42] [INFO] [MAGVIEW] MagView started
[2025-10-06 20:24:42] [INFO] [REGISTRY] Component state changed: MagView {"old_state":"starting","new_state":"running"}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.state_changed {"name":"MagView","old_state":"starting","new_state":"running"}
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: component.started {"name":"MagView"}
[2025-10-06 20:24:42] [INFO] [LIFECYCLE] Component started: MagView
[2025-10-06 20:24:42] [INFO] [BOOT] ✓ All services started
[2025-10-06 20:24:42] [INFO] [BOOT] [STAGE 6] SYSTEM READY
[2025-10-06 20:24:42] [DEBUG] [STATE] State saved
[2025-10-06 20:24:42] [DEBUG] [STATE] State saved
[2025-10-06 20:24:42] [DEBUG] [STATE] State saved
[2025-10-06 20:24:42] [DEBUG] [EVENTBUS] Event emitted: system.ready

╔════════════════════════════════════════════════════════════╗
║              MoBoMini System Ready                         ║
╠════════════════════════════════════════════════════════════╣
║ Components Loaded: 4                                    ║
║  ✓ MagDB                                               ║
║  ✓ MagPuma                                             ║
║  ✓ MagGate                                             ║
║  ✓ MagView                                             ║
╠════════════════════════════════════════════════════════════╣
║ System: http://mobo.magflock.test                  ║
╚════════════════════════════════════════════════════════════╝

[2025-10-06 20:24:42] [INFO] [BOOT] === MoBoMini Boot Complete ===
✅ MagGate loaded
✅ MagPuma loaded

✅ Routes registered: 4

TEST 1: Normal GET request
----------------------------
Status: 404
Content: {
    "error": "Not Found",
    "path": "\/"
}

TEST 2: SQL Injection attempt
----------------------------
IP Trust Score: 100
Threat Level: 0
Threats:

TEST 3: Bot detection
----------------------------
Is Bot: NO
Bot Type: unknown
Confidence: 38%

TEST 4: Good bot detection
----------------------------
Is Good Bot: YES
Bot Type: googlebot
Confidence: 95%

TEST 5: Fingerprinting
----------------------------
Fingerprint: 940d6e6a21449a6e4b70a1861e6d0c79...

TEST 6: Adaptive response
----------------------------
Action: allow
Rate Limit: 1000/minute
Should Log: NO

🎉🎉🎉 ALL TESTS COMPLETE! 🐆🔥💪