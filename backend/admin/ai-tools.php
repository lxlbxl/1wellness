<?php
require_once 'auth.php';
require_once '../classes/Database.php';
require_once '../classes/AutomationOrchestrator.php';

$db = Database::getInstance();
$message = '';
$error = '';

/**
 * AI Tool Functions
 */
function getAiTools($db)
{
    if ($db->isUsingFileStorage()) {
        // Fallback or mock for file storage mode
        return [];
    } else {
        try {
            $stmt = $db->query("SELECT * FROM ai_tools ORDER BY created_at DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

function saveAiTool($db, $tool)
{
    if ($db->isUsingFileStorage()) {
        return false;
    } else {
        try {
            if (isset($tool['id']) && !empty($tool['id'])) {
                // Update
                $db->update('ai_tools', [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'json_schema' => $tool['json_schema'],
                    'endpoint_url' => $tool['endpoint_url'],
                    'is_active' => $tool['is_active']
                ], "id = :id", [':id' => $tool['id']]);
            } else {
                // Create
                $db->insert('ai_tools', [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'json_schema' => $tool['json_schema'],
                    'endpoint_url' => $tool['endpoint_url'],
                    'is_active' => $tool['is_active']
                ]);
            }
            return true;
        } catch (Exception $e) {
            error_log("Save AI Tool Error: " . $e->getMessage());
            return false;
        }
    }
}

function deleteAiTool($db, $id)
{
    if ($db->isUsingFileStorage()) {
        return false;
    }
    return $db->delete('ai_tools', "id = :id", [':id' => $id]);
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save':
                $tool = [
                    'id' => $_POST['id'] ?? null,
                    'name' => $_POST['name'] ?? 'Untitled Tool',
                    'description' => $_POST['description'] ?? '',
                    'json_schema' => $_POST['json_schema'] ?? '{}',
                    'endpoint_url' => $_POST['endpoint_url'] ?? '',
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ];

                // Validate JSON Schema
                $decoded = json_decode($tool['json_schema']);
                if ($decoded === null) {
                    $error = 'Invalid JSON Schema format.';
                } else {
                    if (saveAiTool($db, $tool)) {
                        $message = 'Tool saved successfully.';
                    } else {
                        $error = 'Failed to save tool.';
                    }
                }
                break;

            case 'delete':
                if (isset($_POST['id'])) {
                    if (deleteAiTool($db, $_POST['id'])) {
                        $message = 'Tool deleted successfully.';
                    } else {
                        $error = 'Failed to delete tool.';
                    }
                }
                break;

            case 'toggle_active':
                if (isset($_POST['id']) && isset($_POST['status'])) {
                    $db->update('ai_tools', ['is_active' => $_POST['status']], "id = :id", [':id' => $_POST['id']]);
                    $message = 'Tool status updated.';
                }
                break;

            case 'test_tool':
                $toolName = $_POST['tool_name'] ?? '';
                $toolArgsJson = $_POST['tool_args'] ?? '{}';

                try {
                    $args = json_decode($toolArgsJson, true);
                    if ($args === null && json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception("Invalid JSON formatting in arguments.");
                    }

                    $automation = new AutomationOrchestrator();
                    // Direct execution of the tool
                    $result = $automation->executeAgentAction($toolName, $args ?: []);

                    $message = "Tool executed successfully. Output: " . substr(json_encode($result), 0, 100) . "...";
                    // Store full result in session or variable to show in modal
                    $testResult = json_encode($result, JSON_PRETTY_PRINT);
                } catch (Exception $e) {
                    $error = "Test failed: " . $e->getMessage();
                    $testResult = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

$tools = getAiTools($db);
$pageTitle = 'AI Tools - 1wellness Admin';
include 'includes/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back
            to Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">AI Agent Tools</h2>
        <p class="text-[#6B7C70] mt-1">Manage capabilities for the Reasoning Engine</p>
    </div>
    <div>
        <button onclick="openModal()"
            class="px-4 py-2 bg-[#2C3E35] text-white rounded-xl text-sm font-medium hover:bg-[#1a2621] transition-colors shadow-lg shadow-[#2C3E35]/20">
            <i class="fas fa-plus mr-2"></i> Add New Tool
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-8 p-4 bg-[#F2F4F1] border border-[#A4B4A6] text-[#2C3E35] rounded-xl flex items-center">
        <i class="fas fa-check-circle text-[#2C3E35] mr-3"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mb-8 p-4 bg-[#FDF1E8] border border-[#D97757] text-[#D97757] rounded-xl flex items-center">
        <i class="fas fa-exclamation-circle mr-3"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Tools List -->
<div class="luxury-card overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-[#EAEAE5] bg-[#FAFAF8]">
        <h3 class="flex items-center text-lg font-serif text-[#2C3E35]">
            <i class="fas fa-robot mr-2 text-[#D97757] text-sm"></i>Registered Capabilities
        </h3>
    </div>

    <?php if (empty($tools)): ?>
        <div class="p-12 text-center text-[#6B7C70] bg-white">
            <div class="bg-[#F2F4F1] w-16 h-16 rounded-full flex items-center justify-center text-[#A4B4A6] mx-auto mb-4">
                <i class="fas fa-hammer text-2xl"></i>
            </div>
            <p class="text-lg font-serif text-[#2C3E35] mb-2">No tools configured</p>
            <p class="text-sm mb-6">Add tools to give your AI Agent new abilities.</p>
            <button onclick="openModal()"
                class="px-4 py-2 bg-white border border-[#EAEAE5] text-[#2C3E35] rounded-lg text-sm font-medium hover:bg-[#F2F4F1] transition-colors">
                Create your first tool
            </button>
        </div>
    <?php else: ?>
        <div class="divide-y divide-[#EAEAE5]">
            <?php foreach ($tools as $tool): ?>
                <div
                    class="p-6 flex flex-col md:flex-row md:items-start justify-between gap-4 bg-white hover:bg-[#FDFCF8] transition-colors">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-1">
                            <h4 class="text-lg font-serif text-[#2C3E35]">
                                <?php echo htmlspecialchars($tool['name']); ?>
                            </h4>

                            <form method="POST" action="" class="inline">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="id" value="<?php echo $tool['id']; ?>">
                                <input type="hidden" name="status" value="<?php echo $tool['is_active'] ? '0' : '1'; ?>">
                                <button type="submit"
                                    class="px-2 py-0.5 text-xs font-semibold rounded-full cursor-pointer transition-colors <?php echo $tool['is_active'] ? 'bg-[#E3E8E1] text-[#2C3E35] hover:bg-[#D1D8CE]' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'; ?>">
                                    <?php echo $tool['is_active'] ? 'Active' : 'Inactive'; ?>
                                </button>
                            </form>
                        </div>
                        <p class="text-[#6B7C70] text-sm mb-3">
                            <?php echo htmlspecialchars($tool['description']); ?>
                        </p>

                        <div class="flex gap-4 text-xs font-mono text-gray-500">
                            <?php if ($tool['endpoint_url']): ?>
                                <span title="Webhook URL"><i class="fas fa-link mr-1"></i>
                                    <?php echo htmlspecialchars(substr($tool['endpoint_url'], 0, 40)) . (strlen($tool['endpoint_url']) > 40 ? '...' : ''); ?>
                                </span>
                            <?php else: ?>
                                <span title="Internal Method"><i class="fas fa-code mr-1"></i> Internal Handler</span>
                            <?php endif; ?>

                            <span title="Schema Size"><i class="fas fa-file-code mr-1"></i>
                                <?php echo strlen($tool['json_schema']); ?> chars
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 mt-1">
                        <button onclick='editTool(<?php echo json_encode($tool); ?>)'
                            class="p-2 text-[#6B7C70] hover:text-[#2C3E35] transition-colors" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>

                        <button onclick='testTool(<?php echo json_encode($tool); ?>)'
                            class="p-2 text-[#6B7C70] hover:text-[#00BCD4] transition-colors" title="Test Tool">
                            <i class="fas fa-play"></i>
                        </button>

                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this tool?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($tool['id']); ?>">
                            <button type="submit" class="p-2 text-[#6B7C70] hover:text-[#D97757] transition-colors"
                                title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Tool Modal -->
<div id="toolModal" class="fixed inset-0 z-50 hidden bg-[#2C3E35]/50 overflow-y-auto h-full w-full backdrop-blur-sm"
    onclick="if(event.target === this) closeModal()">
    <div
        class="relative top-10 mx-auto p-0 border-0 w-full max-w-2xl shadow-2xl rounded-2xl bg-white overflow-hidden mb-10">
        <div class="p-6 border-b border-[#EAEAE5] bg-[#FAFAF8] flex justify-between items-center">
            <h3 class="text-xl font-serif text-[#2C3E35]" id="modalTitle">Add New AI Tool</h3>
            <button onclick="closeModal()" class="text-[#A4B4A6] hover:text-[#D97757]">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form method="POST" action="" class="p-6 space-y-4">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="toolId" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#2C3E35] mb-1">Tool Name (Function Name)</label>
                    <input type="text" name="name" id="toolName" required placeholder="e.g. get_order_status"
                        pattern="[a-zA-Z0-9_]+" title="Only letters, numbers and underscores allowed"
                        class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono">
                    <p class="text-xs text-gray-500 mt-1">Must be snake_case (e.g. send_whatsapp)</p>
                </div>

                <div class="flex items-end mb-2">
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" name="is_active" id="toolActive" value="1" checked
                            class="form-checkbox h-5 w-5 text-[#2C3E35] rounded border-[#A4B4A6] focus:ring-[#2C3E35]">
                        <span class="text-[#2C3E35] font-medium">Tool Active</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-[#2C3E35] mb-1">Description</label>
                <input type="text" name="description" id="toolDescription" required
                    placeholder="What does this tool do?"
                    class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
            </div>

            <div>
                <label class="block text-sm font-medium text-[#2C3E35] mb-1">External Webhook URL (Optional)</label>
                <input type="url" name="endpoint_url" id="toolUrl" placeholder="https://api.example.com/webhook"
                    class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                <p class="text-xs text-gray-500 mt-1">Leave empty if this is handled internally by
                    AutomationOrchestrator.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-[#2C3E35] mb-1">JSON Schema (Parameters)</label>
                <textarea name="json_schema" id="toolSchema" required rows="8"
                    class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm bg-gray-50"
                    placeholder='{
  "type": "object",
  "properties": {
    "order_id": { "type": "string" }
  },
  "required": ["order_id"]
}'></textarea>
                <div class="flex justify-between mt-1">
                    <p class="text-xs text-gray-500">Must be valid JSON Schema Draft.</p>
                    <button type="button" onclick="formatJson()" class="text-xs text-[#D97757] hover:underline">Format
                        JSON</button>
                </div>
            </div>

            <div class="pt-4 flex justify-end gap-3 border-t border-[#EAEAE5] mt-4">
                <button type="button" onclick="closeModal()"
                    class="px-4 py-2 border border-[#EAEAE5] rounded-lg text-[#6B7C70] hover:bg-[#F2F4F1] transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="px-6 py-2 bg-[#2C3E35] text-white font-medium rounded-lg hover:bg-[#1a2621] transition-colors shadow-md">
                    Save Tool
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('toolModal').classList.remove('hidden');
        document.getElementById('modalTitle').textContent = 'Add New AI Tool';
        document.getElementById('toolId').value = '';
        document.getElementById('toolName').value = '';
        document.getElementById('toolDescription').value = '';
        document.getElementById('toolUrl').value = '';
        document.getElementById('toolActive').checked = true;
        document.getElementById('toolSchema').value = '{\n  "type": "object",\n  "properties": {\n    "arg_name": { "type": "string" }\n  },\n  "required": ["arg_name"]\n}';
    }

    function closeModal() {
        document.getElementById('toolModal').classList.add('hidden');
    }

    function editTool(tool) {
        document.getElementById('toolModal').classList.remove('hidden');
        document.getElementById('modalTitle').textContent = 'Edit AI Tool';

        document.getElementById('toolId').value = tool.id;
        document.getElementById('toolName').value = tool.name;
        document.getElementById('toolDescription').value = tool.description;
        document.getElementById('toolUrl').value = tool.endpoint_url || '';
        document.getElementById('toolActive').checked = tool.is_active == 1;
        document.getElementById('toolSchema').value = tool.json_schema;
    }

    function formatJson() {
        const textarea = document.getElementById('toolSchema');
        try {
            const val = JSON.parse(textarea.value);
            textarea.value = JSON.stringify(val, null, 2);
        } catch (e) {
            alert('Invalid JSON');
        }
    }

    function testTool(tool) {
        document.getElementById('testModal').classList.remove('hidden');
        document.getElementById('testToolNameDisplay').textContent = tool.name;
        document.getElementById('testToolNameInput').value = tool.name;

        // Generate placeholder JSON from schema if possible, else empty object
        document.getElementById('testToolArgs').value = '{\n  \n}';
        document.getElementById('testResultArea').classList.add('hidden');
    }

    function closeTestModal() {
        document.getElementById('testModal').classList.add('hidden');
    }
</script>

<!-- Test Tool Modal -->
<div id="testModal" class="fixed inset-0 z-50 hidden bg-[#2C3E35]/50 overflow-y-auto h-full w-full backdrop-blur-sm"
    onclick="if(event.target === this) closeTestModal()">
    <div
        class="relative top-10 mx-auto p-0 border-0 w-full max-w-2xl shadow-2xl rounded-2xl bg-white overflow-hidden mb-10">
        <div class="p-6 border-b border-[#EAEAE5] bg-[#FAFAF8] flex justify-between items-center">
            <h3 class="text-xl font-serif text-[#2C3E35]">Test Tool: <span id="testToolNameDisplay"
                    class="font-mono text-[#D97757]"></span></h3>
            <button onclick="closeTestModal()" class="text-[#A4B4A6] hover:text-[#D97757]">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="p-6 space-y-4">
            <!-- If we have a result from a previous submission, show it -->
            <?php if (isset($testResult)): ?>
                <div id="testResultResult" class="mb-4">
                    <label class="block text-sm font-medium text-[#2C3E35] mb-1">Execution Result</label>
                    <div
                        class="bg-[#1A2620] text-[#A4B4A6] p-4 rounded-lg font-mono text-xs overflow-auto max-h-60 whitespace-pre-wrap">
                        <?php echo htmlspecialchars($testResult); ?></div>
                </div>
                <!-- Auto-open modal if result exists -->
                <script>document.getElementById('testModal').classList.remove('hidden');</script>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="action" value="test_tool">
                <input type="hidden" name="tool_name" id="testToolNameInput" value="">

                <div>
                    <label class="block text-sm font-medium text-[#2C3E35] mb-1">Test Arguments (JSON)</label>
                    <textarea name="tool_args" id="testToolArgs" rows="6"
                        class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm bg-gray-50 mb-2"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-[#EAEAE5]">
                    <button type="button" onclick="closeTestModal()"
                        class="px-4 py-2 border border-[#EAEAE5] rounded-lg text-[#6B7C70] hover:bg-[#F2F4F1] transition-colors">
                        Close
                    </button>
                    <button type="submit"
                        class="px-6 py-2 bg-[#00BCD4] text-white font-medium rounded-lg hover:bg-[#00ACC1] transition-colors shadow-md flex items-center gap-2">
                        <i class="fas fa-play"></i> Run Test
                    </button>
                </div>
            </form>

            <div id="testResultArea" class="hidden"></div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>