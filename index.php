<?php
session_start();

if (isset($_GET['action']) && $_GET['action'] === 'new') {
    unset($_SESSION['exam_questions']);
    unset($_SESSION['current_question']);
    unset($_SESSION['user_answers']);
    unset($_SESSION['scores']);
    unset($_SESSION['feedback']);
}

$exam_in_progress = (isset($_SESSION['exam_questions']) && !empty($_SESSION['exam_questions']));
$first_question_id = null;
if ($exam_in_progress) {
    $first_question_id = array_key_first($_SESSION['user_answers']); // الحصول على أول ID
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بدء تدريب امتحان الكتابة B1</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        #loading-indicator { display: none; text-align: center; margin-top: 20px; font-weight: bold; color: #4a148c; }
        #loading-indicator .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #4a148c; border-radius: 50%; width: 25px; height: 25px; animation: spin 1s linear infinite; display: inline-block; margin-left: 10px; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="container">
        <h1>محاكاة امتحان الكتابة Staatsexamen Nt2 (B1)</h1>
        <p>هذا التدريب سيساعدك على الاستعداد لامتحان الكتابة الرسمي.</p>
        <p>ستحصل على 12 سؤالاً متنوعاً بنفس مستوى صعوبة الامتحان الحقيقي.</p>
        <p>يمكنك النقر على أي كلمة هولندية في السؤال لترى ترجمتها العربية.</p>
        <p>بعد الإجابة على كل سؤال (ما عدا النماذج)، يمكنك طلب تقييم لإجابتك.</p>

        <form action="generate_exam.php" method="post" id="start-exam-form" style="text-align: center; margin-bottom: 20px;">
            <button type="submit" class="nav-btn">بدء امتحان جديد</button>
        </form>

        <div id="loading-indicator">
            جاري إنشاء الامتحان، يرجى الانتظار...
            <div class="spinner"></div>
        </div>

        <?php if ($exam_in_progress && $first_question_id !== null): ?>
            <p style="text-align:center; margin-top: 20px;">لديك امتحان قيد التقدم.</p>
            <form action="exam.php" method="get" style="text-align: center;">
                 <?php // استخدام ID السؤال الحالي أو الأول للمتابعة ?>
                <input type="hidden" name="q" value="<?php echo isset($_SESSION['current_question']) ? $_SESSION['current_question'] : $first_question_id; ?>">
                <button type="submit" class="nav-btn next-btn">متابعة الامتحان الحالي</button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('start-exam-form').addEventListener('submit', function() {
            document.getElementById('loading-indicator').style.display = 'block';
            const button = this.querySelector('button');
            button.disabled = true;
            button.textContent = 'جاري التحميل...';
            // إخفاء زر المتابعة إن وجد
             const continueForm = document.querySelector('form[action="exam.php"]');
             if(continueForm) continueForm.style.display = 'none';
        });
    </script>

</body>
</html>