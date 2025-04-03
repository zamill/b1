<?php
session_start();
require_once '../config.php'; // تأكد من المسار الصحيح
require_once 'gemini_handler.php'; // تأكد من المسار الصحيح

header('Content-Type: application/json');

if (!isset($_POST['question_id']) || !isset($_POST['answer'])) {
    echo json_encode(['success' => false, 'error' => 'بيانات ناقصة.']);
    exit;
}

$question_id = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT);
$user_answer = trim($_POST['answer']);

if ($question_id === false || !isset($_SESSION['exam_questions']) || !isset($_SESSION['user_answers'][$question_id])) {
    echo json_encode(['success' => false, 'error' => 'رقم سؤال غير صالح أو لا يوجد امتحان نشط.']);
    exit;
}

$question_data = null;
foreach ($_SESSION['exam_questions'] as $q) {
    if ($q['id'] === $question_id) {
        $question_data = $q;
        break;
    }
}

if (!$question_data) {
    echo json_encode(['success' => false, 'error' => 'لم يتم العثور على بيانات السؤال.']);
    exit;
}

// لا تقم بالتقييم إذا كان نوع السؤال هو نموذج
if ($question_data['type'] === 'form') {
     echo json_encode(['success' => false, 'error' => 'لا يتوفر التحقق التلقائي لأسئلة النماذج.']);
     exit;
}

$prompt_html = $question_data['prompt_html'];
$evaluation_criteria = $question_data['evaluation_criteria'] ?? [];

// استدعاء Gemini لتقييم الإجابة
$feedback_text = evaluate_answer_with_gemini(
    $prompt_html,
    $evaluation_criteria,
    $user_answer,
    GEMINI_API_KEY,
    GEMINI_API_URL_GENERATE,
    $question_data // تمرير بيانات السؤال كاملة
);

if ($feedback_text) {
    // محاولة استخراج النقاط المقترحة
    $suggested_score = null;
    $matches = [];
    $points_for_question = $question_data['points'] ?? 0; // النقاط الممكنة للسؤال

    // البحث عن "النقاط المقترحة: X/Y" حيث Y يجب أن يطابق نقاط السؤال
    if (preg_match('/النقاط المقترحة:\s*(\d+)\s*\/\s*(' . $points_for_question . ')/u', $feedback_text, $matches)) {
        $suggested_score = (int)$matches[1];
    } elseif (preg_match('/النقاط المقترحة:\s*(\d+)\s*\/\s*\d+/u', $feedback_text, $matches)) {
         // محاولة احتياطية إذا كان الرقم Y مختلفاً (قد يحدث إذا أخطأ Gemini)
         $suggested_score = (int)$matches[1];
         error_log("Score mismatch from Gemini for QID {$question_id}: Expected /{$points_for_question}, Got /" . ($matches[2] ?? 'unknown'));
         // تأكد من أن النقاط المقترحة لا تتجاوز الحد الأقصى
         if ($suggested_score > $points_for_question) {
             $suggested_score = $points_for_question;
         }
    }

    // تخزين التقييم والنقاط في الجلسة
    $_SESSION['feedback'][$question_id] = $feedback_text;
    $_SESSION['scores'][$question_id] = $suggested_score; // قد تكون null

    echo json_encode(['success' => true, 'feedback' => $feedback_text]);
} else {
    echo json_encode(['success' => false, 'error' => 'فشل الحصول على التقييم من Gemini. حاول مرة أخرى.']);
}

exit;
?>