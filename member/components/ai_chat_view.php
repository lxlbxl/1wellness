<?php
/**
 * AI Specialist Chat View
 * Condition-aware AI chat with hard-coded compliance guardrail.
 */
$aiName    = htmlspecialchars(($conditionCfg['sub_brand'] ?? '1wellness') . ' Specialist');
$aiIcon    = htmlspecialchars($conditionCfg['icon'] ?? 'sparkles');
$aiTagline = htmlspecialchars($conditionCfg['tagline'] ?? 'Your wellness guide');
?>
<div id="ai_chat" class="view-section hidden" style="height: calc(100vh - 9rem);">
<div class="flex flex-col h-full">
    <!-- Header -->
    <div class="flex items-center justify-between pb-6 border-b border-sage-100 flex-shrink-0">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-sage-500 flex items-center justify-center text-white shadow-lg shadow-sage-500/20">
                <i data-lucide="<?php echo $aiIcon; ?>" class="w-7 h-7"></i>
            </div>
            <div>
                <h2 class="text-2xl font-serif text-sage-600"><?php echo $aiName; ?></h2>
                <div class="flex items-center gap-2 mt-0.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span>
                    <p class="text-sage-400 text-xs"><?php echo $aiTagline; ?></p>
                </div>
            </div>
        </div>
        <button id="aiChatClearBtn" class="text-[10px] font-bold uppercase tracking-widest text-sage-300 hover:text-coral-400 transition-colors px-4 py-2 rounded-xl hover:bg-coral-50">
            Clear chat
        </button>
    </div>

    <!-- Disclaimer -->
    <div class="mt-5 mb-4 flex-shrink-0 bg-amber-50 border border-amber-200 rounded-2xl px-5 py-3 flex items-start gap-3">
        <i data-lucide="info" class="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0"></i>
        <p class="text-amber-700 text-xs leading-relaxed">
            This AI provides general wellness guidance only &mdash; it is <strong>not a medical professional</strong> and cannot diagnose, treat, or prescribe. Always consult your doctor for medical concerns.
        </p>
    </div>

    <!-- Messages area -->
    <div id="aiChatMessages" class="flex-1 overflow-y-auto space-y-5 py-4 pr-2 custom-scrollbar">
        <!-- Welcome bubble -->
        <div class="flex items-start gap-3 ai-message">
            <div class="w-9 h-9 rounded-xl bg-sage-500 flex items-center justify-center text-white flex-shrink-0">
                <i data-lucide="<?php echo $aiIcon; ?>" class="w-4 h-4"></i>
            </div>
            <div class="bg-white border border-sage-50 rounded-3xl rounded-tl-md px-5 py-4 max-w-[75%] shadow-sm">
                <p class="text-sage-600 text-sm leading-relaxed">
                    Hi! I'm your <?php echo $aiName; ?>. I'm here to support your wellness journey with guidance on nutrition, lifestyle, and natural health practices.<br><br>
                    What can I help you with today?
                </p>
            </div>
        </div>
    </div>

    <!-- Typing indicator (hidden by default) -->
    <div id="aiTypingIndicator" class="hidden flex items-start gap-3 py-2 flex-shrink-0">
        <div class="w-9 h-9 rounded-xl bg-sage-500 flex items-center justify-center text-white flex-shrink-0">
            <i data-lucide="<?php echo $aiIcon; ?>" class="w-4 h-4"></i>
        </div>
        <div class="bg-white border border-sage-50 rounded-3xl rounded-tl-md px-5 py-4 shadow-sm">
            <div class="flex gap-1 items-center h-4">
                <span class="w-2 h-2 rounded-full bg-sage-300 animate-bounce" style="animation-delay:0ms"></span>
                <span class="w-2 h-2 rounded-full bg-sage-300 animate-bounce" style="animation-delay:150ms"></span>
                <span class="w-2 h-2 rounded-full bg-sage-300 animate-bounce" style="animation-delay:300ms"></span>
            </div>
        </div>
    </div>

    <!-- Input area -->
    <div class="flex-shrink-0 pt-4 border-t border-sage-100 mt-2">
        <form id="aiChatForm" class="flex gap-3 items-end">
            <textarea
                id="aiChatInput"
                rows="1"
                placeholder="Ask about nutrition, lifestyle, or your wellness protocol&hellip;"
                class="flex-1 resize-none bg-white border border-sage-100 rounded-2xl px-5 py-4 text-sm text-sage-600 placeholder-sage-300 focus:outline-none focus:border-sage-400 transition-colors leading-relaxed max-h-32 overflow-y-auto"
                style="min-height:3.25rem;"
            ></textarea>
            <button
                type="submit"
                id="aiChatSendBtn"
                class="w-14 h-14 rounded-2xl bg-sage-500 text-white flex items-center justify-center shadow-lg shadow-sage-500/20 hover:scale-105 active:scale-95 transition-all flex-shrink-0 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <i data-lucide="send" class="w-5 h-5"></i>
            </button>
        </form>
        <p class="text-[10px] text-sage-300 text-center mt-2 uppercase tracking-widest">AI may make mistakes &bull; Not medical advice</p>
    </div>
</div>

<script>
(function () {
    const messagesEl  = document.getElementById('aiChatMessages');
    const form        = document.getElementById('aiChatForm');
    const input       = document.getElementById('aiChatInput');
    const sendBtn     = document.getElementById('aiChatSendBtn');
    const typing      = document.getElementById('aiTypingIndicator');
    const clearBtn    = document.getElementById('aiChatClearBtn');

    // Chat history kept in JS memory (not persisted)
    let history = [];
    let sending  = false;

    // Auto-resize textarea
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 128) + 'px';
    });

    // Send on Enter (Shift+Enter = newline)
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!sending) form.dispatchEvent(new Event('submit'));
        }
    });

    clearBtn.addEventListener('click', () => {
        history = [];
        // Remove all non-welcome bubbles
        const bubbles = messagesEl.querySelectorAll('.user-message, .ai-message:not(:first-child)');
        bubbles.forEach(b => b.remove());
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const msg = input.value.trim();
        if (!msg || sending) return;

        sending = true;
        sendBtn.disabled = true;
        input.value = '';
        input.style.height = '';

        appendMessage('user', msg);
        history.push({ role: 'user', content: msg });

        // Show typing indicator
        typing.classList.remove('hidden');
        messagesEl.scrollTop = messagesEl.scrollHeight;

        let reply = '';
        let flagged = false;

        try {
            const res = await fetch('/member/api/ai-chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: msg, history: history.slice(0, -1) }),
            });
            const data = await res.json();
            if (data.success) {
                reply   = data.reply;
                flagged = data.flagged || false;
            } else {
                reply = data.error || 'Something went wrong. Please try again.';
            }
        } catch (err) {
            reply = 'Unable to reach the AI service. Please check your connection and try again.';
        }

        typing.classList.add('hidden');
        appendMessage('assistant', reply, flagged);
        history.push({ role: 'assistant', content: reply });

        sending = false;
        sendBtn.disabled = false;
        input.focus();
    });

    function appendMessage(role, text, flagged) {
        const isUser = role === 'user';
        const wrapper = document.createElement('div');
        wrapper.className = isUser ? 'flex items-end justify-end gap-3 user-message' : 'flex items-start gap-3 ai-message';

        const ai_icon = window.CONDITION_CFG?.icon || 'sparkles';

        if (isUser) {
            wrapper.innerHTML = `
                <div class="bg-sage-500 text-white rounded-3xl rounded-br-md px-5 py-4 max-w-[75%] shadow-sm">
                    <p class="text-sm leading-relaxed">${escapeHtml(text)}</p>
                </div>
                <div class="w-9 h-9 rounded-xl bg-sage-100 flex items-center justify-center text-sage-400 flex-shrink-0">
                    <i data-lucide="user" class="w-4 h-4"></i>
                </div>`;
        } else {
            const borderClass = flagged ? 'border-amber-200 bg-amber-50' : 'border-sage-50 bg-white';
            const textClass   = flagged ? 'text-amber-800' : 'text-sage-600';
            wrapper.innerHTML = `
                <div class="w-9 h-9 rounded-xl bg-sage-500 flex items-center justify-center text-white flex-shrink-0">
                    <i data-lucide="${ai_icon}" class="w-4 h-4"></i>
                </div>
                <div class="border ${borderClass} rounded-3xl rounded-tl-md px-5 py-4 max-w-[75%] shadow-sm">
                    <p class="${textClass} text-sm leading-relaxed">${formatAiText(text)}</p>
                </div>`;
        }

        messagesEl.appendChild(wrapper);
        messagesEl.scrollTop = messagesEl.scrollHeight;

        if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [wrapper] });
    }

    function escapeHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
    }

    function formatAiText(s) {
        // Convert **bold** and newlines; escape HTML first
        return escapeHtml(s)
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n\n/g, '</p><p class="mt-3">')
            .replace(/\n/g, '<br>');
    }
})();
</script>
</div><!-- /.flex.flex-col inner wrapper -->
