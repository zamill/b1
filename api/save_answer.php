<?php
// api/save_answer.php

session_start(); // ابدأ الجلسة للوصول إلى بياناتها

header('Content-Type: application/json'); // دائماً أرجع JSON من نقاط النهاية API

// تحقق إذا كان هناك امتحان نشط وإذا تم إرسال البيانات المطلوبة
if (!isset($_SESSION['user_answers']) || !isset($_POST['question_id']) || !isset($_POST['answer'])) {
    echo json_encode(['success' => false, 'error' => 'Missing data or no active exam session.']);
    exit;
}

// تنقية المدخلات
$question_id = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT);
// يمكنك استخدام filter_var أو طرق أخرى لتنقية الإجابة إذا لزم الأمر،
// لكن بما أنها ستُخزن فقط ويتم تنقيتها عند العرض، trim كافية هنا.
$user_answer = trim($_POST['answer']);

// تحقق من صحة رقم السؤال (يجب أن يكون ضمن نطاق مفاتيح مصفوفة الإجابات)
if ($question_id === false || !array_key_exists($question_id, $_SESSION['user_answers'])) {
     echo json_encode(['success' => false, 'error' => 'Invalid question ID.']);
     exit;
}

// قم بتحديث الإجابة في مصفوفة الجلسة
$_SESSION['user_answers'][$question_id] = $user_answer;

// أرجع رسالة نجاح
echo json_encode(['success' => true, 'message' => 'Answer saved for question ' . $question_id]);
exit;

?>