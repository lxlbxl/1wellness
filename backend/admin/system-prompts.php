<?php
require_once 'auth.php';
require_once '../classes/Database.php';

$db = Database::getInstance();
$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_prompt':
            case 'update_prompt':
                $key = isset($_POST['prompt_key']) ? trim($_POST['prompt_key']) : '';
                $text = isset($_POST['prompt_text']) ? trim($_POST['prompt_text']) : '';
                $desc = isset($_POST['description']) ? trim($_POST['description']) : '';
                $id = isset($_POST['prompt_id']) ? (int) $_POST['prompt_id'] : 0;

                if (empty($key) || empty($text)) {
                    $error = "Prompt Key and Text are required.";
                } else {
                    if ($_POST['action'] === 'create_prompt') {
                        try {
                            // Check if key exists
                            $existing = $db->fetch("SELECT id FROM system_prompts WHERE prompt_key = ?", [$key]);
                            if ($existing) {
                                $error = "A prompt with this key already exists.";
                            } else {
                                $db->insert('system_prompts', [
                                    'prompt_key' => $key,
                                    'prompt_text' => $text,
                                    'description' => $desc
                                ]);
                                $message = "System prompt created successfully.";
                            }
                        } catch (Exception $e) {
                            $error = "Error adding prompt: " . $e->getMessage();
                        }
                    } else {
                        try {
                            $db->update('system_prompts', [
                                'prompt_text' => $text,
                                'description' => $desc
                            ], "id = $id");
                            $message = "System prompt updated successfully.";
                        } catch (Exception $e) {
                            $error = "Error updating prompt: " . $e->getMessage();
                        }
                    }
                }
                break;

            case 'delete_prompt':
                $id = isset($_POST['prompt_id']) ? (int) $_POST['prompt_id'] : 0;
                if ($id) {
                    try {
                        $db->query("DELETE FROM system_prompts WHERE id = ?", [$id]);
                        $message = "System prompt deleted successfully.";
                    } catch (Exception $e) {
                        $error = "Error deleting prompt: " . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Fetch Prompts
$prompts = $db->fetchAll("SELECT * FROM system_prompts ORDER BY prompt_key ASC");

$pageTitle = 'System Prompts - 1wellness Admin';
include 'includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back
            to Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">System Prompts</h2>
        <p class="text-[#6B7C70] mt-1">Manage the core instructions for your AI agents.</p>
    </div>
    <div class="flex gap-3">
        <a href="ai-playground.php"
            class="bg-white text-[#2C3E35] border border-[#2C3E35] px-6 py-3 rounded-xl hover:bg-[#F2F4F1] transition-all flex items-center gap-2">
            <i class="fas fa-flask"></i> Open Playground
        </a>
        <button onclick="openModal()"
            class="bg-[#2C3E35] text-white px-6 py-3 rounded-xl hover:bg-[#1a2621] transition-all shadow-lg shadow-[#2C3E35]/20 flex items-center gap-2">
            <i class="fas fa-plus"></i> Add New Prompt
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

<div class="grid grid-cols-1 gap-6">
    <?php if (empty($prompts)): ?>
        <div class="luxury-card p-12 text-center text-[#6B7C70]">
            <i class="fas fa-robot text-4xl mb-4 opacity-30"></i>
            <p>No system prompts defined yet.</p>
            <p class="text-sm mt-2">Create one to define agent behaviors.</p>
        </div>
    <?php else: ?>
        <?php foreach ($prompts as $prompt): ?>
            <div class="luxury-card p-6 transition-all hover:shadow-md border border-[#EAEAE5] bg-white group relative">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <h3 class="text-xl font-serif text-[#2C3E35] font-bold">
                                <?php echo htmlspecialchars($prompt['prompt_key']); ?>
                            </h3>
                            <span class="text-xs font-mono bg-[#F2F4F1] text-[#6B7C70] px-2 py-1 rounded">ID:
                                <?php echo $prompt['id']; ?>
                            </span>
                        </div>
                        <?php if ($prompt['description']): ?>
                            <p class="text-sm text-[#6B7C70] mt-1">
                                <?php echo htmlspecialchars($prompt['description']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="flex gap-2">
                        <button onclick='editPrompt(<?php echo json_encode($prompt); ?>)'
                            class="text-[#6B7C70] hover:text-[#2C3E35] p-2 rounded-full hover:bg-[#F2F4F1] transition-colors"
                            title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this prompt?');"
                            class="inline">
                            <input type="hidden" name="action" value="delete_prompt">
                            <input type="hidden" name="prompt_id" value="<?php echo $prompt['id']; ?>">
                            <button type="submit"
                                class="text-[#D97757] hover:text-[#B54D2F] p-2 rounded-full hover:bg-[#FDF1E8] transition-colors"
                                title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <div
                    class="bg-[#FAFAF8] p-4 rounded-lg border border-[#EAEAE5] max-h-40 overflow-y-auto text-xs font-mono text-[#4A5D52] whitespace-pre-wrap">
                    <?php echo htmlspecialchars($prompt['prompt_text']); ?>
                </div>

                <div class="mt-4 text-[10px] text-[#A4B4A6] flex justify-between">
                    <span>Created:
                        <?php echo isset($prompt['created_at']) ? date('M j, Y', strtotime($prompt['created_at'])) : '-'; ?>
                    </span>
                    <span>Updated:
                        <?php echo isset($prompt['updated_at']) ? date('M j, Y H:i', strtotime($prompt['updated_at'])) : '-'; ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div id="promptModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-[#2C3E35]/40 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div
                class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-[#EAEAE5]">
                <form method="POST" id="promptForm">
                    <input type="hidden" name="action" id="formAction" value="create_prompt">
                    <input type="hidden" name="prompt_id" id="promptId" value="">

                    <div class="bg-white px-8 py-6 border-b border-[#EAEAE5]">
                        <h3 class="text-2xl font-serif text-[#2C3E35]" id="modalTitle">Add New System Prompt</h3>
                        <p class="text-sm text-[#6B7C70] mt-1">Define the persistent instructions for an agent persona.
                        </p>
                    </div>

                    <div class="p-8 space-y-6">
                        <div>
                            <label class="block text-sm font-bold text-[#2C3E35] mb-2 uppercase tracking-wide">Prompt
                                Key</label>
                            <input type="text" name="prompt_key" id="promptKey" required
                                class="luxury-input w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm"
                                placeholder="e.g. pcos_expert_agent">
                            <p class="text-xs text-[#6B7C70] mt-2">Unique identifier used in code to load this prompt.
                            </p>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-bold text-[#2C3E35] mb-2 uppercase tracking-wide">Description</label>
                            <input type="text" name="description" id="promptDesc"
                                class="luxury-input w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]"
                                placeholder="Brief description of this agent's role...">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-[#2C3E35] mb-2 uppercase tracking-wide">System
                                Instructions</label>
                            <textarea name="prompt_text" id="promptText" rows="12" required
                                class="luxury-input w-full px-4 py-3 border border-[#EAEAE5] rounded-xl focus:outline-none focus:border-[#2C3E35] text-[#2C3E35] font-mono text-sm leading-relaxed"
                                placeholder="You are a helpful assistant..."></textarea>
                        </div>
                    </div>

                    <div class="bg-[#FAFAF8] px-8 py-4 flex justify-end gap-3 border-t border-[#EAEAE5]">
                        <button type="button" onclick="closeModal()"
                            class="px-6 py-2.5 border border-[#EAEAE5] text-[#6B7C70] font-medium rounded-xl hover:bg-white hover:text-[#2C3E35] transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-8 py-2.5 bg-[#2C3E35] text-white font-medium rounded-xl hover:bg-[#1a2621] transition-colors shadow-lg shadow-[#2C3E35]/20">
                            Save Prompt
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('promptModal').classList.remove('hidden');
        document.getElementById('modalTitle').textContent = 'Add New System Prompt';
        document.getElementById('formAction').value = 'create_prompt';
        document.getElementById('promptId').value = '';
        document.getElementById('promptKey').value = '';
        document.getElementById('promptKey').readOnly = false;
        document.getElementById('promptDesc').value = '';
        document.getElementById('promptText').value = '';
    }

    function editPrompt(prompt) {
        document.getElementById('promptModal').classList.remove('hidden');
        document.getElementById('modalTitle').textContent = 'Edit System Prompt';
        document.getElementById('formAction').value = 'update_prompt';
        document.getElementById('promptId').value = prompt.id;
        document.getElementById('promptKey').value = prompt.prompt_key;
        document.getElementById('promptKey').readOnly = true; // Key cannot be changed
        document.getElementById('promptKey').classList.add('bg-gray-100');
        document.getElementById('promptDesc').value = prompt.description;
        document.getElementById('promptText').value = prompt.prompt_text;
    }

    function closeModal() {
        document.getElementById('promptModal').classList.add('hidden');
        document.getElementById('promptKey').classList.remove('bg-gray-100');
    }
</script>

<?php include 'includes/footer.php'; ?>