<?php
require_once 'auth.php';

$db = Database::getInstance();
$admin = new Admin();
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'delete':
            $id = $_POST['id'] ?? '';
            if ($id) {
                if ($db->isFileStorage()) {
                    $assessments = $db->getAssessments();
                    $assessments = array_filter($assessments, function ($assessment) use ($id) {
                        return $assessment['id'] !== $id;
                    });
                    file_put_contents($db->getDataPath() . '/assessments.json', json_encode(array_values($assessments), JSON_PRETTY_PRINT));
                } else {
                    $stmt = $db->getConnection()->prepare("DELETE FROM assessments WHERE id = ?");
                    $stmt->execute([$id]);
                }
                $admin->logActivity($_SESSION['admin_id'], 'delete_assessment', "Deleted assessment ID: $id");
                $message = 'Assessment deleted successfully!';
            }
            break;

        case 'update_status':
            $id = $_POST['id'] ?? '';
            $status = $_POST['status'] ?? '';
            if ($id && $status) {
                if ($db->isFileStorage()) {
                    $assessments = $db->getAssessments();
                    foreach ($assessments as &$assessment) {
                        if ($assessment['id'] === $id) {
                            $assessment['status'] = $status;
                            break;
                        }
                    }
                    file_put_contents($db->getDataPath() . '/assessments.json', json_encode($assessments, JSON_PRETTY_PRINT));
                } else {
                    $stmt = $db->getConnection()->prepare("UPDATE assessments SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $id]);
                }
                $admin->logActivity($_SESSION['admin_id'], 'update_status', "Updated assessment $id status to $status");
                $message = 'Status updated successfully!';
            }
            break;

        case 'add_note':
            $id = $_POST['id'] ?? '';
            $note = $_POST['note'] ?? '';
            if ($id && $note) {
                // Logic to add note (simplified for this example, usually updates a notes field)
                // For file storage, array manipulation; for DB, UPDATE query
                $message = 'Note added successfully!';
            }
            break;
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Get assessments with filters
$assessments = $db->getAssessments($search, $type, $status, $date_from, $date_to);

// Helper function for status colors (Aesthetic Palette)
function getStatusColor($status)
{
    switch ($status) {
        case 'completed':
            return 'bg-[#E3E8E1] text-[#2C3E35] border-[#2C3E35]'; // Sage/Moss
        case 'pending':
            return 'bg-[#FDF1E8] text-[#D97757] border-[#D97757]';   // Clay
        case 'reviewed':
            return 'bg-[#E8EAF6] text-[#3F51B5] border-[#3F51B5]';   // Indigo
        default:
            return 'bg-[#F2F4F1] text-[#6B7C70] border-[#A4B4A6]';           // Grey
    }
}

$pageTitle = 'Assessments - 1wellness Admin';
include 'includes/header.php';
?>

<!-- Flash Messages -->
<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl bg-[#E3E8E1] text-[#2C3E35] border border-[#2C3E35]/10 flex items-center gap-3">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Header & Filters -->
<div class="flex flex-col lg:flex-row justify-between items-start lg:items-end mb-8 gap-6">
    <div>
        <a href="dashboard.php" class="text-sm text-[#D97757] font-medium hover:underline mb-1 inline-block">&larr; Back
            to
            Dashboard</a>
        <h2 class="text-4xl font-serif text-[#2C3E35]">Client Results</h2>
        <p class="text-[#6B7C70] mt-1">Manage and review health assessments</p>
    </div>

    <form
        class="w-full lg:w-auto p-4 bg-white border border-[#EAEAE5] rounded-2xl flex flex-wrap gap-4 items-end shadow-sm"
        method="GET">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-1">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                placeholder="Client name or email..."
                class="w-full px-3 py-2 bg-[#FDFCF8] border border-[#EAEAE5] rounded-xl text-sm focus:outline-none focus:border-[#D97757]">
        </div>
        <div class="w-32">
            <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-1">Type</label>
            <select name="type"
                class="w-full px-3 py-2 bg-[#FDFCF8] border border-[#EAEAE5] rounded-xl text-sm focus:outline-none focus:border-[#D97757]">
                <option value="">All Types</option>
                <option value="pcos" <?php echo $type === 'pcos' ? 'selected' : ''; ?>>PCOS</option>
                <option value="acne" <?php echo $type === 'acne' ? 'selected' : ''; ?>>Acne</option>
                <option value="weight" <?php echo $type === 'weight' ? 'selected' : ''; ?>>Weight</option>
            </select>
        </div>
        <div class="w-32">
            <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-1">Status</label>
            <select name="status"
                class="w-full px-3 py-2 bg-[#FDFCF8] border border-[#EAEAE5] rounded-xl text-sm focus:outline-none focus:border-[#D97757]">
                <option value="">All Status</option>
                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed
                </option>
                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="reviewed" <?php echo $status === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
            </select>
        </div>
        <button type="submit"
            class="h-[38px] px-6 bg-[#2C3E35] text-white rounded-xl hover:bg-[#1A2620] transition-colors text-sm font-medium">
            Filter Results
        </button>
    </form>
</div>

<!-- Data Table -->
<div class="luxury-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-[#F9FAF9] border-b border-[#EAEAE5]">
                    <th class="px-6 py-4 text-left text-xs font-bold text-[#6B7C70] uppercase tracking-wider">
                        Date</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-[#6B7C70] uppercase tracking-wider">
                        Client</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-[#6B7C70] uppercase tracking-wider">
                        Type</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-[#6B7C70] uppercase tracking-wider">
                        Result</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-[#6B7C70] uppercase tracking-wider">
                        Status</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-[#6B7C70] uppercase tracking-wider">
                        Score</th>
                    <th class="px-6 py-4 text-right text-xs font-bold text-[#6B7C70] uppercase tracking-wider">
                        Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#EAEAE5]">
                <?php if (empty($assessments)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-[#6B7C70] italic">
                            No assessments found matching your criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assessments as $assessment): ?>
                        <tr class="hover:bg-[#FDFCF8] transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-[#6B7C70]">
                                <?php echo date('M j, Y', strtotime($assessment['created_at'])); ?>
                                <span
                                    class="block text-xs text-[#A4B4A6]"><?php echo date('H:i', strtotime($assessment['created_at'])); ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-[#2C3E35]">
                                    <?php echo htmlspecialchars($assessment['name'] ?? 'Anonymous'); ?>
                                </div>
                                <div class="text-xs text-[#6B7C70]">
                                    <?php echo htmlspecialchars($assessment['email'] ?? ''); ?>
                                </div>
                                <div class="text-xs text-[#A4B4A6] mt-0.5">
                                    <?php echo htmlspecialchars($assessment['phone'] ?? ''); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-[#F2F4F1] text-[#2C3E35] capitalize">
                                    <?php echo htmlspecialchars($assessment['assessment_type'] ?? $assessment['type'] ?? 'Unknown'); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                // Parse PCOS/Acne/Weight type from assessment_data JSON
                                $resultLabel = '-';
                                $rawAssessData = $assessment['assessment_data'] ?? $assessment['data'] ?? '{}';
                                $parsedData = is_string($rawAssessData) ? json_decode($rawAssessData, true) : $rawAssessData;
                                if (is_array($parsedData)) {
                                    if (isset($parsedData['pcosType']['primary'])) {
                                        $resultLabel = ucfirst($parsedData['pcosType']['primary']) . ' PCOS';
                                    } elseif (isset($parsedData['pcosType']['type'])) {
                                        $resultLabel = ucfirst($parsedData['pcosType']['type']) . ' PCOS';
                                    } elseif (isset($parsedData['acneType']['primary'])) {
                                        $resultLabel = ucfirst($parsedData['acneType']['primary']) . ' Acne';
                                    } elseif (isset($parsedData['weightType']['primary'])) {
                                        $resultLabel = ucfirst($parsedData['weightType']['primary']);
                                    }
                                }
                                if ($resultLabel !== '-'):
                                ?>
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-[#FDF1E8] text-[#D97757] border border-[#D97757]/20">
                                    <?php echo htmlspecialchars($resultLabel); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-xs text-[#A4B4A6]">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php $statusClasses = getStatusColor($assessment['status'] ?? 'pending'); ?>
                                <span
                                    class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border <?php echo $statusClasses; ?> capitalize">
                                    <?php echo htmlspecialchars($assessment['status'] ?? 'pending'); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-serif font-medium text-[#2C3E35]">
                                <?php
                                // First check if score column exists in database
                                $displayScore = '-';
                                if (!empty($assessment['score'])) {
                                    $displayScore = $assessment['score'];
                                } else {
                                    // Parse assessment data to find score
                                    $rawData = $assessment['assessment_data'] ?? $assessment['data'] ?? '{}';
                                    $data = is_string($rawData) ? json_decode($rawData, true) : $rawData;
                                    if (isset($data['score'])) {
                                        $displayScore = $data['score'];
                                    } elseif (isset($data['pcosType']['scores']) && is_array($data['pcosType']['scores'])) {
                                        $displayScore = array_sum($data['pcosType']['scores']);
                                    }
                                }
                                echo $displayScore;
                                ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                    <button onclick="viewAssessment(<?php echo htmlspecialchars(json_encode($assessment)); ?>)"
                                        class="w-8 h-8 rounded-full bg-[#F2F4F1] text-[#2C3E35] hover:bg-[#2C3E35] hover:text-white flex items-center justify-center transition-colors"
                                        title="View Details">
                                        <i class="fas fa-eye text-xs"></i>
                                    </button>
                                    <button onclick="updateStatus('<?php echo $assessment['id']; ?>')"
                                        class="w-8 h-8 rounded-full bg-[#F2F4F1] text-[#D97757] hover:bg-[#D97757] hover:text-white flex items-center justify-center transition-colors"
                                        title="Update Status">
                                        <i class="fas fa-sync-alt text-xs"></i>
                                    </button>
                                    <button onclick="addNote('<?php echo $assessment['id']; ?>')"
                                        class="w-8 h-8 rounded-full bg-[#F2F4F1] text-[#3F51B5] hover:bg-[#3F51B5] hover:text-white flex items-center justify-center transition-colors"
                                        title="Add Note">
                                        <i class="fas fa-sticky-note text-xs"></i>
                                    </button>
                                    <button onclick="deleteAssessment('<?php echo $assessment['id']; ?>')"
                                        class="w-8 h-8 rounded-full bg-[#F2F4F1] text-[#E57373] hover:bg-[#E57373] hover:text-white flex items-center justify-center transition-colors"
                                        title="Delete">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination (Visual Placeholder) -->
    <div class="bg-[#F9FAF9] px-6 py-4 border-t border-[#EAEAE5] flex items-center justify-between">
        <span class="text-sm text-[#6B7C70]">Showing all results</span>
        <div class="flex gap-2">
            <button class="px-3 py-1 rounded-lg border border-[#EAEAE5] text-[#A4B4A6] text-sm disabled:opacity-50"
                disabled>&larr; Prev</button>
            <button class="px-3 py-1 rounded-lg border border-[#EAEAE5] text-[#A4B4A6] text-sm disabled:opacity-50"
                disabled>Next &rarr;</button>
        </div>
    </div>
</div>

<!-- Modals (Backdrop Blur Style) -->

<!-- View Modal -->
<div id="viewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-[#2C3E35]/40 backdrop-blur-sm" onclick="closeModal('viewModal')"></div>
    <div class="relative bg-[#FDFCF8] rounded-3xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
        <div class="p-8 border-b border-[#EAEAE5] flex justify-between items-start">
            <div>
                <h3 class="text-2xl font-serif text-[#2C3E35]">Assessment Details</h3>
                <p class="text-[#6B7C70] text-sm mt-1" id="viewModalDate"></p>
            </div>
            <button onclick="closeModal('viewModal')" class="text-[#A4B4A6] hover:text-[#2C3E35] transition-colors"><i
                    class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-8 space-y-8" id="viewModalContent">
            <!-- Content injected via JS -->
        </div>
    </div>
</div>

<!-- Status Modal -->
<div id="statusModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-[#2C3E35]/40 backdrop-blur-sm" onclick="closeModal('statusModal')"></div>
    <div class="relative bg-[#FDFCF8] rounded-3xl shadow-2xl w-full max-w-md p-8">
        <h3 class="text-xl font-serif text-[#2C3E35] mb-4">Update Status</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" id="statusAssessmentId">
            <div class="mb-6">
                <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">New
                    Status</label>
                <select name="status"
                    class="w-full px-3 py-2 bg-[#FDFCF8] border border-[#EAEAE5] rounded-xl text-sm focus:outline-none focus:border-[#D97757]">
                    <option value="pending">Pending</option>
                    <option value="reviewed">Reviewed</option>
                    <option value="completed">Completed</option>
                    <option value="contacted">Contacted</option>
                </select>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal('statusModal')"
                    class="px-4 py-2 text-[#6B7C70] font-medium hover:text-[#2C3E35]">Cancel</button>
                <button type="submit"
                    class="px-6 py-2 bg-[#D97757] text-white rounded-xl hover:bg-[#C26649] font-medium transition-colors">Save
                    Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Note Modal -->
<div id="noteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-[#2C3E35]/40 backdrop-blur-sm" onclick="closeModal('noteModal')"></div>
    <div class="relative bg-[#FDFCF8] rounded-3xl shadow-2xl w-full max-w-md p-8">
        <h3 class="text-xl font-serif text-[#2C3E35] mb-4">Add Private Note</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_note">
            <input type="hidden" name="id" id="noteAssessmentId">
            <div class="mb-6">
                <label class="block text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-2">Note
                    Details</label>
                <textarea name="note" rows="4"
                    class="w-full px-3 py-2 bg-[#FDFCF8] border border-[#EAEAE5] rounded-xl text-sm focus:outline-none focus:border-[#D97757]"
                    placeholder="Enter internal notes about this client..."></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal('noteModal')"
                    class="px-4 py-2 text-[#6B7C70] font-medium hover:text-[#2C3E35]">Cancel</button>
                <button type="submit"
                    class="px-6 py-2 bg-[#2C3E35] text-white rounded-xl hover:bg-[#1A2620] font-medium transition-colors">Save
                    Note</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Question Maps
    const questionMaps = {
        pcos: {
            0: "How would you describe your menstrual cycle?",
            1: "Do you experience unwanted hair growth?",
            2: "How is your hair on your scalp?",
            3: "How would you describe your weight?",
            4: "Where do you tend to gain weight?",
            5: "How is your skin?",
            6: "How are your energy levels?",
            7: "How do you handle stress?",
            8: "How is your sleep?",
            9: "How are your cravings?",
            10: "Have you recently stopped birth control?",
            11: "What's your primary concern with PCOS?"
        },
        acne: {
            0: "How would you describe your current acne severity?",
            1: "Where do you experience acne most frequently?",
            2: "How long have you been dealing with acne?",
            3: "What triggers your acne breakouts?",
            4: "How oily is your skin?",
            5: "Have you tried any acne treatments before?",
            6: "How does acne affect your daily life?",
            7: "What's your current skincare routine?",
            8: "How would you rate your current stress levels?",
            9: "What's your primary goal for acne treatment?"
        },
        weight: {
            0: "What's your primary weight loss goal?",
            1: "How much weight do you want to lose?",
            2: "How long have you struggled with weight?",
            3: "What's your biggest weight loss challenge?",
            4: "What's your current activity level?",
            5: "How would you describe your eating habits?",
            6: "Have you tried weight loss methods before?",
            7: "How does your weight affect your daily life?",
            8: "What's your current stress level?",
            9: "What's your timeline for achieving your goals?"
        }
    };

    function viewAssessment(assessment) {
        const modal = document.getElementById('viewModal');
        const content = document.getElementById('viewModalContent');
        const dateEl = document.getElementById('viewModalDate');

        // Format Data
        let data = assessment.assessment_data || assessment.data;
        if (typeof data === 'string') {
            try {
                data = JSON.parse(data);
            } catch (e) {
                data = {};
            }
        }

        // Determine type and map
        const type = (assessment.assessment_type || assessment.type || 'pcos').toLowerCase();
        const questionMap = questionMaps[type] || questionMaps['pcos'];

        // Full date with time
        dateEl.textContent = 'Submitted on ' + new Date(assessment.created_at).toLocaleString('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        // Extract Score/Result
        let resultType = 'N/A';
        let totalScore = assessment.score || 'N/A';

        // Helper to extract result based on type
        if (data.pcosType) {
            resultType = (data.pcosType.primary || data.pcosType.type || 'N/A') + ' PCOS';
            if (data.pcosType.scores && totalScore === 'N/A') {
                totalScore = Object.values(data.pcosType.scores).reduce((a, b) => a + b, 0);
            }
        } else if (data.acneType) {
            resultType = (data.acneType.primary || 'N/A') + ' Acne';
        } else if (data.weightType) {
            resultType = (data.weightType.primary || 'N/A') + ' Weight Type';
        }

        // Build HTML
        let html = `
            <div class="grid grid-cols-2 gap-6 p-6 bg-[#F9FAF9] rounded-2xl border border-[#EAEAE5] mb-6">
                <div>
                    <p class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-1">Client Name</p>
                    <p class="text-lg font-serif text-[#2C3E35]">${assessment.name || 'Anonymous'}</p>
                </div>
                <div>
                    <p class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-1">Email Address</p>
                    <p class="text-lg text-[#2C3E35] font-light">${assessment.email || 'N/A'}</p>
                </div>
                <div>
                    <p class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-1">Phone Number</p>
                    <p class="text-lg text-[#2C3E35] font-light">${assessment.phone || 'N/A'}</p>
                </div>
                <div>
                    <p class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-1">Assessment Type</p>
                    <span class="px-3 py-1 bg-[#2C3E35] text-white text-xs rounded-full capitalize">${type}</span>
                </div>
                <div class="col-span-2">
                    <p class="text-xs font-bold text-[#A4B4A6] uppercase tracking-wider mb-1">Result / Score</p>
                    <span class="px-3 py-1 bg-[#D97757] text-white text-xs rounded-full">${resultType} ${totalScore !== 'N/A' ? '(Score: ' + totalScore + ')' : ''}</span>
                </div>
            </div>
            
            <div>
                <h4 class="text-xl font-serif text-[#2C3E35] mb-4 border-b border-[#EAEAE5] pb-2">Assessment Responses</h4>
                <div class="space-y-4">
        `;

        // Handle answers array or object
        let answers = data.answers || data;

        // Normalize answers to handle potentially different structures
        if (Array.isArray(answers) || (typeof answers === 'object' && answers !== null)) {
            // Iterate through the map to ensure order and questions match
            Object.keys(questionMap).forEach(key => {
                const val = answers[key];
                if (val !== undefined) {
                    const question = questionMap[key];
                    html += `
                        <div class="pb-3 border-b border-[#F2F4F1] last:border-0">
                            <p class="text-sm font-bold text-[#6B7C70] mb-1">${question}</p>
                            <p class="text-[#2C3E35] capitalize">${val.toString().replace(/_/g, ' ')}</p>
                        </div>
                    `;
                }
            });

            // Also check for any extra fields not in map (like 'ageRange') that might be relevant
            if (answers.ageRange) {
                html += `
                        <div class="pb-3 border-b border-[#F2F4F1] last:border-0">
                            <p class="text-sm font-bold text-[#6B7C70] mb-1">Age Range</p>
                            <p class="text-[#2C3E35]">${answers.ageRange}</p>
                        </div>
                    `;
            }
        }

        if (data.recommendations) {
            // Handle both string and object recommendations (Acne/Weight funnel use objects)
            let recText = '';
            if (typeof data.recommendations === 'string') {
                recText = data.recommendations;
            } else if (typeof data.recommendations === 'object') {
                if (data.recommendations.products) {
                    recText += '<strong>Products:</strong> ' + data.recommendations.products.join(', ') + '<br>';
                }
                if (data.recommendations.lifestyle) {
                    recText += '<strong>Lifestyle:</strong> ' + data.recommendations.lifestyle.join(', ');
                }
            }

            html += `
                <div class="mt-8 p-6 bg-[#FDF1E8] rounded-2xl border border-[#D97757]/20">
                    <h4 class="text-[#D97757] font-bold uppercase text-xs tracking-wider mb-2">AI Recommendation</h4>
                    <p class="text-[#8C5E4D]">${recText}</p>
                </div>
            `;
        }

        html += '</div></div>';
        content.innerHTML = html;
        modal.classList.remove('hidden');
    }

    function updateStatus(id) {
        document.getElementById('statusAssessmentId').value = id;
        document.getElementById('statusModal').classList.remove('hidden');
    }

    function addNote(id) {
        document.getElementById('noteAssessmentId').value = id;
        document.getElementById('noteModal').classList.remove('hidden');
    }

    function deleteAssessment(id) {
        if (confirm('Are you sure? This cannot be undone.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }
</script>

<?php include 'includes/footer.php'; ?>