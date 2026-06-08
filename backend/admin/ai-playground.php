<?php
require_once 'auth.php';
require_once '../classes/Database.php';
require_once '../classes/AIOrchestrator.php';

$db = Database::getInstance();
$orchestrator = new AIOrchestrator();

// Fetch available System Prompts for selection
$prompts = $db->fetchAll("SELECT * FROM system_prompts ORDER BY prompt_key ASC");

$chatHistory = [];
$selectedPrompt = isset($_POST['system_prompt']) ? $_POST['system_prompt'] : (count($prompts) > 0 ? $prompts[0]['prompt_key'] : '');
$userMessage = '';
$aiResponse = null;
$debugLog = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $userMessage = $_POST['message'];
    $selectedPrompt = $_POST['system_prompt'];

    // Execute AI with Debug History
    try {
        $result = $orchestrator->generateResponse(
            $selectedPrompt,
            $userMessage,
            [], // variables
            [], // chat history (not persisted in this playground for simplicity, essentially 1-turn test)
            [], // extra tools
            true // Return Full History
        );

        if (is_array($result)) {
            $aiResponse = $result['response'];
            $debugLog = $result['history'];
        } else {
            $aiResponse = $result;
        }
    } catch (Exception $e) {
        $aiResponse = "Error: " . $e->getMessage();
    }
}

$pageTitle = 'AI Playground - 1wellness Admin';
include 'includes/header.php';
?>

<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back
            to Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">AI Playground</h2>
        <p class="text-[#6B7C70] mt-1">Test your Agents and watch them think.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Configuration & Chat -->
    <div class="lg:col-span-1 space-y-6">
        <div class="luxury-card p-6">
            <h3 class="font-serif text-[#2C3E35] text-xl mb-4">Configuration</h3>
            <form method="POST" action="">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-[#2C3E35] mb-1">Select Persona (System Prompt)</label>
                    <select name="system_prompt"
                        class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]">
                        <?php foreach ($prompts as $p): ?>
                            <option value="<?php echo $p['prompt_key']; ?>" <?php echo $selectedPrompt === $p['prompt_key'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['prompt_key']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-[#2C3E35] mb-1">User Message</label>
                    <textarea name="message" rows="5" required placeholder="Ask the agent something..."
                        class="luxury-input w-full px-4 py-2 border border-[#EAEAE5] rounded-lg focus:outline-none focus:border-[#2C3E35] text-[#2C3E35]"><?php echo htmlspecialchars($userMessage); ?></textarea>
                </div>

                <button type="submit"
                    class="w-full px-6 py-3 bg-[#2C3E35] text-white font-medium rounded-xl hover:bg-[#1a2621] transition-colors shadow-md">
                    <i class="fas fa-paper-plane mr-2"></i> Send to Agent
                </button>
            </form>
        </div>

        <?php if ($aiResponse): ?>
            <div class="luxury-card p-6 bg-[#E3E8E1] border-none">
                <h3 class="font-serif text-[#2C3E35] text-lg mb-2">Final Answer</h3>
                <div class="prose prose-sm text-[#2C3E35]">
                    <?php echo nl2br(htmlspecialchars(is_array($aiResponse) ? json_encode($aiResponse) : $aiResponse)); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Reasoning Log -->
    <div class="lg:col-span-2">
        <div class="luxury-card p-6 min-h-[600px] flex flex-col">
            <h3 class="font-serif text-[#2C3E35] text-xl mb-6">Execution Log (Reasoning Engine)</h3>

            <?php if (empty($debugLog)): ?>
                <div
                    class="flex-1 flex flex-col items-center justify-center text-[#A4B4A6] p-12 text-center border-2 border-dashed border-[#EAEAE5] rounded-xl">
                    <i class="fas fa-brain text-4xl mb-4"></i>
                    <p>Send a message to see the AI's thought process here.</p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($debugLog as $step): ?>

                        <?php if ($step['role'] === 'system'): ?>
                            <div class="p-4 bg-[#FAFAF8] border border-[#EAEAE5] rounded-xl opacity-75">
                                <div class="text-[10px] font-bold text-[#6B7C70] uppercase tracking-widest mb-2">System Instruction
                                </div>
                                <div class="text-xs text-[#6B7C70] font-mono h-20 overflow-y-auto whitespace-pre-wrap">
                                    <?php echo htmlspecialchars($step['content']); ?>
                                </div>
                            </div>

                        <?php elseif ($step['role'] === 'user'): ?>
                            <div class="flex justify-end">
                                <div class="bg-[#2C3E35] text-white p-4 rounded-2xl rounded-tr-none max-w-[80%]">
                                    <div class="text-[10px] font-bold text-[#A4B4A6] uppercase tracking-widest mb-1">User</div>
                                    <p class="text-sm">
                                        <?php echo nl2br(htmlspecialchars($step['content'])); ?>
                                    </p>
                                </div>
                            </div>

                        <?php elseif ($step['role'] === 'assistant'): ?>

                            <?php if (isset($step['tool_calls'])): ?>
                                <!-- Tool Call Request (Reasoning) -->
                                <div class="bg-[#FFF8E1] border border-[#FFECB3] p-4 rounded-2xl">
                                    <div class="flex items-center gap-2 mb-2">
                                        <i class="fas fa-lightbulb text-[#FFC107]"></i>
                                        <span class="text-[10px] font-bold text-[#ffb300] uppercase tracking-widest">Thought /
                                            Action</span>
                                    </div>
                                    <?php if (isset($step['content']) && $step['content']): ?>
                                        <div class="text-sm text-[#795548] mb-3 italic">
                                            "
                                            <?php echo htmlspecialchars($step['content']); ?>"
                                        </div>
                                    <?php endif; ?>

                                    <div class="space-y-2">
                                        <?php foreach ($step['tool_calls'] as $toolCall): ?>
                                            <div class="bg-white/50 p-2 rounded border border-[#FFECB3] font-mono text-xs text-[#6D4C41]">
                                                <span class="font-bold">
                                                    <?php echo htmlspecialchars($toolCall['function']['name']); ?>
                                                </span>
                                                <span class="text-gray-500">(
                                                    <?php echo htmlspecialchars($toolCall['function']['arguments']); ?>)
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Final Answer in History (Duplicate of display above, but keeps timeline) -->
                                <div class="flex justify-start">
                                    <div class="bg-[#F2F4F1] text-[#2C3E35] p-4 rounded-2xl rounded-tl-none max-w-[90%]">
                                        <div class="text-[10px] font-bold text-[#6B7C70] uppercase tracking-widest mb-1">AI Agent</div>
                                        <div class="prose prose-sm">
                                            <?php echo nl2br(htmlspecialchars($step['content'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php elseif ($step['role'] === 'tool'): ?>
                            <!-- Tool Output (Observation) -->
                            <div class="ml-8 bg-[#E0F7FA] border border-[#B2EBF2] p-4 rounded-2xl relative">
                                <div class="absolute -left-4 top-4 w-8 h-px bg-[#B2EBF2]"></div>
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-eye text-[#00BCD4]"></i>
                                    <span class="text-[10px] font-bold text-[#0097A7] uppercase tracking-widest">Observation (Tool
                                        Output)</span>
                                </div>
                                <div class="font-mono text-xs text-[#006064] overflow-x-auto bg-white/50 p-2 rounded">
                                    <?php
                                    $content = $step['content'];
                                    // Try to pretty print JSON
                                    $decoded = json_decode($content);
                                    if ($decoded !== null) {
                                        echo "<pre>" . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT)) . "</pre>";
                                    } else {
                                        echo htmlspecialchars($content);
                                    }
                                    ?>
                                </div>
                            </div>

                        <?php endif; ?>

                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>