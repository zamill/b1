<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['exam_questions']) || empty($_SESSION['exam_questions'])) {
    header('Location: index.php');
    exit;
}

$current_q_id = isset($_GET['q']) ? (int)$_GET['q'] : null;
$question_ids = array_keys($_SESSION['user_answers']); // الحصول على IDs من مصفوفة الإجابات

// تحديد السؤال الحالي والأول والأخير والتالي والسابق بناءً على IDs
$first_q_id = $question_ids[0] ?? null;
if ($current_q_id === null || !in_array($current_q_id, $question_ids)) {
    $current_q_id = $first_q_id; // إذا لم يتم التحديد أو غير صالح، اذهب للأول
}

if ($current_q_id === null) {
     die("خطأ: لا يمكن تحديد السؤال الأول."); // لا يوجد أسئلة
}

$_SESSION['current_question'] = $current_q_id; // حفظ ID الحالي

$question_data = null;
$translations = "{}";
foreach ($_SESSION['exam_questions'] as $q) {
    if ($q['id'] === $current_q_id) {
        $question_data = $q;
        $translations = json_encode($q['word_translations'] ?? [], JSON_UNESCAPED_UNICODE);
        break;
    }
}

if (!$question_data) {
    die("خطأ: لم يتم العثور على بيانات السؤال رقم " . $current_q_id);
}

// حساب التقدم والتنقل
$total_questions = count($question_ids);
$current_index = array_search($current_q_id, $question_ids);
$next_q_id = $question_ids[$current_index + 1] ?? null;
$prev_q_id = ($current_index > 0) ? $question_ids[$current_index - 1] : null;
$current_q_number = $current_index + 1; // رقم السؤال التسلسلي للعرض

$user_answer = $_SESSION['user_answers'][$current_q_id] ?? '';
$feedback = $_SESSION['feedback'][$current_q_id] ?? '';

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سؤال <?php echo $current_q_number; ?> من <?php echo $total_questions; ?> - امتحان الكتابة B1</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .translateable { cursor: pointer; text-decoration: underline; text-decoration-style: dotted; color: blue; }
        #translation-popup { position: absolute; background-color: #f9f9f9; border: 1px solid #ccc; padding: 5px 10px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: none; z-index: 1000; font-size: 0.9em; }
        .feedback-section { margin-top: 20px; padding: 15px; background-color: #eef; border: 1px solid #ccd; border-radius: 5px; }
        .loading-spinner { display: inline-block; border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 20px; height: 20px; animation: spin 1s linear infinite; margin-left: 10px; vertical-align: middle; display: none; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="container exam-container">
        <div class="exam-header">
            <h2>Staatsexamen Nt2: Openbaar examen Schrijven | (تدريب <?php echo date('Y'); ?>)</h2>
            <h3>سؤال <?php echo $current_q_number; ?> من <?php echo $total_questions; ?> (<?php echo $question_data['points']; ?> نقاط)</h3>
        </div>

        <div class="question-area" id="question-content">
            <?php echo $question_data['prompt_html']; // عرض HTML السؤال ?>
        </div>

        <div class="action-buttons">
             <?php // التحقق من نوع السؤال قبل عرض زر التحقق ?>
            <?php if ($question_data['type'] !== 'form'): ?>
                <button id="check-answer-btn" data-question-id="<?php echo $current_q_id; ?>">
                    تحقق من الإجابة واحصل على نصائح
                </button>
                <div class="loading-spinner" id="loading-spinner"></div>
            <?php else: ?>
                <p style="font-style: italic; color: #666;">(لا يتوفر التحقق التلقائي لأسئلة تعبئة النماذج)</p>
            <?php endif; ?>
        </div>

        <div id="feedback-area" class="feedback-section" style="<?php echo empty($feedback) ? 'display: none;' : ''; ?>">
            <h4>التقييم والنصائح:</h4>
            <p id="feedback-text"><?php echo nl2br(htmlspecialchars($feedback)); ?></p>
        </div>

        <div class="navigation-buttons">
            <?php if ($prev_q_id): ?>
                <a href="exam.php?q=<?php echo $prev_q_id; ?>" class="nav-btn prev-btn">< السابق</a>
            <?php else: ?>
                 <span class="nav-btn disabled">< السابق</span> <?php // زر معطل ?>
            <?php endif; ?>

            <?php if ($next_q_id): ?>
                <a href="exam.php?q=<?php echo $next_q_id; ?>" class="nav-btn next-btn">التالي ></a>
            <?php else: ?>
                <a href="results.php" class="nav-btn finish-btn">إنهاء وعرض النتيجة</a>
            <?php endif; ?>
             <a href="index.php?action=new" class="nav-btn reset-btn" onclick="return confirm('هل أنت متأكد أنك تريد بدء امتحان جديد؟ ستفقد تقدمك الحالي.');">بدء امتحان جديد</a>
        </div>
    </div>

    <div id="translation-popup"></div>

    <script>
        const wordTranslations = <?php echo $translations; ?>;
        const currentQuestionId = <?php echo $current_q_id; ?>;
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>