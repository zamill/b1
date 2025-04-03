<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['exam_questions']) || !isset($_SESSION['user_answers']) || !isset($_SESSION['feedback'])) {
    header('Location: index.php');
    exit;
}

$exam_questions_assoc = []; // مصفوفة ارتباطية باستخدام ID كمفتاح لسهولة الوصول
foreach($_SESSION['exam_questions'] as $q) {
    $exam_questions_assoc[$q['id']] = $q;
}
$user_answers = $_SESSION['user_answers'];
$feedback = $_SESSION['feedback'];
$scores = $_SESSION['scores']; // النقاط المقترحة المخزنة

$total_possible_points = 0;
$total_earned_points = 0;
$possible_points_for_scored_questions = 0; // النقاط الممكنة فقط للأسئلة التي تم تقييمها

foreach ($exam_questions_assoc as $q_id => $q) {
    $q_points = (int)($q['points'] ?? 0);
    $total_possible_points += $q_points;

    if (isset($scores[$q_id]) && is_numeric($scores[$q_id])) {
         $earned = (int)$scores[$q_id];
         // تأكد من أن النقاط المكتسبة لا تتجاوز نقاط السؤال
         $earned = min($earned, $q_points);
         $total_earned_points += $earned;
         $possible_points_for_scored_questions += $q_points;
    }
}

$percentage = 0;
if ($possible_points_for_scored_questions > 0) { // حساب النسبة بناءً على الأسئلة المقيمة فقط
    $percentage = round(($total_earned_points / $possible_points_for_scored_questions) * 100);
} elseif ($total_possible_points > 0 && count(array_filter($scores)) == 0) {
    // إذا لم يتم تقييم أي سؤال، النسبة صفر
    $percentage = 0;
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نتائج امتحان الكتابة B1</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .result-item { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; background-color: #f9f9f9; border-radius: 5px; }
        .result-item h4 { margin-top: 0; color: #555; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .user-answer-section, .feedback-section-result { margin-top: 10px; }
        .user-answer-section strong, .feedback-section-result strong { display: block; margin-bottom: 5px; color: #333; }
        .user-answer-text { background-color: #fff; border: 1px solid #eee; padding: 10px; min-height: 50px; white-space: pre-wrap; word-wrap: break-word; direction: ltr; text-align: left; } /* جعل النص LTR */
        .feedback-text-result { background-color: #eef; border: 1px solid #ccd; padding: 10px; white-space: pre-wrap; word-wrap: break-word; }
        .no-answer, .no-feedback { color: #888; font-style: italic; }
        .total-score { text-align: center; font-size: 1.2em; font-weight: bold; margin: 20px 0; padding: 15px; background-color: #e0f2f7; border: 1px solid #b0dbe9; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>ملخص نتائج امتحان الكتابة</h1>

        <div class="total-score">
            النقاط المكتسبة المقترحة: <?php echo $total_earned_points; ?>
            (من <?php echo $possible_points_for_scored_questions > 0 ? $possible_points_for_scored_questions : $total_possible_points; ?> نقطة ممكنة <?php echo $possible_points_for_scored_questions > 0 ? 'للأسئلة المقيمة' : 'إجمالاً'; ?>)
            <br>
            النسبة المئوية المقدرة: <?php echo $percentage; ?>%
            <br>
            <small>(ملاحظة: النقاط المقترحة هي تقدير آلي للمساعدة في التعلم وقد تختلف عن التقييم الرسمي. ركز على فهم التقييم النصي.)</small>
        </div>

        <?php foreach ($user_answers as $q_id => $answer): // المرور على الإجابات
            if (!isset($exam_questions_assoc[$q_id])) continue; // تخطي إذا لم نجد بيانات السؤال
            $question = $exam_questions_assoc[$q_id];
            $q_feedback = $feedback[$q_id] ?? null;
            $q_score = $scores[$q_id] ?? null;
            $q_points_possible = $question['points'] ?? 0;
        ?>
            <div class="result-item">
                <h4>
                    سؤال <?php echo $q_id; ?> (<?php echo htmlspecialchars($q_points_possible); ?> نقاط)
                    <?php if ($question['type'] === 'form'): ?>
                         <span style="font-weight:normal; font-size: 0.9em;"> - (نموذج - لا يوجد تقييم آلي)</span>
                    <?php elseif ($q_score !== null): ?>
                        <span style="font-weight:normal; font-size: 0.9em;"> - النقاط المقترحة: <?php echo $q_score; ?>/<?php echo $q_points_possible; ?></span>
                    <?php elseif (!empty($q_feedback)): ?>
                         <span style="font-weight:normal; font-size: 0.9em;"> - (تم التقييم، لم يتم استخراج النقاط)</span>
                    <?php endif; ?>
                </h4>

                <div class="user-answer-section">
                    <strong>إجابتك:</strong>
                    <?php if (!empty($answer)): ?>
                        <div class="user-answer-text"><?php echo nl2br(htmlspecialchars($answer)); ?></div>
                    <?php else: ?>
                        <p class="no-answer">لم تتم الإجابة على هذا السؤال.</p>
                    <?php endif; ?>
                </div>

                <?php // عرض التقييم فقط للأسئلة التي ليست نماذج ?>
                <?php if($question['type'] !== 'form'): ?>
                    <div class="feedback-section-result">
                        <strong>التقييم والنصائح:</strong>
                        <?php if (!empty($q_feedback)): ?>
                            <?php
                                $feedback_display = preg_replace('/النقاط المقترحة:\s*\d+\s*\/\s*\d+/u', '', $q_feedback);
                                $feedback_display = trim($feedback_display);
                            ?>
                            <div class="feedback-text-result"><?php echo nl2br(htmlspecialchars($feedback_display)); ?></div>
                        <?php else: ?>
                            <p class="no-feedback">لم يتم طلب تقييم لهذا السؤال.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="navigation-buttons" style="text-align: center; margin-top: 30px;">
            <a href="index.php?action=new" class="nav-btn reset-btn">بدء امتحان جديد</a>
             <?php $first_q_id = array_key_first($_SESSION['user_answers']); ?>
             <?php if($first_q_id !== null): ?>
                <a href="exam.php?q=<?php echo $first_q_id; ?>" class="nav-btn">مراجعة الأسئلة</a>
             <?php endif; ?>
        </div>
    </div>
</body>
</html>