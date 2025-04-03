<?php
// --- تفعيل عرض الأخطاء وزيادة وقت التنفيذ في البداية المطلقة ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(120); // السماح بـ 120 ثانية (دقيقتين) للتنفيذ

session_start();

// --- التحقق من وجود الملف المطلوب قبل تضمينه ---
$handler_path = __DIR__ . '/api/gemini_handler.php'; // استخدام المسار المطلق أكثر أمانًا
if (!file_exists($handler_path)) {
    die("خطأ فادح: لم يتم العثور على الملف المطلوب 'api/gemini_handler.php'. يرجى التحقق من المسار.");
}
require_once $handler_path;

// --- التحقق من وجود ملف الإعدادات ---
$config_path = __DIR__ . '/config.php';
if (!file_exists($config_path)) {
     die("خطأ فادح: لم يتم العثور على ملف الإعدادات 'config.php'.");
}
require_once $config_path;

// --- التحقق من تعريف ثابت API Key ---
if (!defined('GEMINI_API_KEY') || !defined('GEMINI_API_URL_GENERATE')) {
    die("خطأ فادح: لم يتم تعريف ثوابت API المطلوبة في 'config.php'.");
}


// --- بناء Prompt لـ Gemini لتوليد الأسئلة (النسخة المحسنة) ---
$prompt = "
Generate a set of 12 diverse Dutch B1 level writing exam questions (Staatsexamen Nt2 Programma I).
Closely mimic the style, format, task types, and difficulty shown in the official exam examples (like emails for appointments/complaints/requests, filling forms for registration/information, short descriptions, short answers to specific questions, short messages to colleagues/friends/institutions). Ensure the visual structure in `prompt_html` matches the example types (e.g., email format with Onderwerp/Aan/Van if applicable, clear form fields, simple textarea for descriptions/letters).
Include a mix of short questions (~2 points) and longer tasks (~7-8 points, specify points). Longer tasks require more structured text (email, letter, detailed form). Short tasks are often 1-3 sentences or a few form fields.
For each question, provide:
1. `id`: Unique number 1-12.
2. `type`: ('email', 'form', 'short_text', 'letter', 'description'). Ensure 'form' type includes actual <form> elements with labels and inputs/textareas. Other types should primarily use a single <textarea name='answer[ID]'> for the user's main response.
3. `points`: Points value (e.g., 2, 7, 8).
4. `prompt_html`: The full instruction text and context in basic HTML. Wrap Dutch words for translation in <span class='translateable'>word</span>. Use appropriate HTML for structure (e.g., <p>, <strong>, <label>, <input>, <textarea name='answer[ID]'>). Ensure only ONE primary answer textarea with the correct name format exists for non-form types.
5. `target_audience`: (Optional) e.g., 'colleague', 'teacher', 'municipality', 'friend', 'company'.
6. `word_translations`: JSON object mapping the *exact* words wrapped in <span class='translateable'> to their Arabic translations.
7. `evaluation_criteria`: Brief bullet points (in English or Dutch) on key aspects for B1 evaluation for this *specific* task (e.g., 'Task completion', 'Correct register/tone', 'Key grammar points like word order/verb conjugation', 'Clarity of request/complaint').

Output the entire set as a valid JSON array of 12 objects. Ensure variety based on common B1 situations (work, study, daily life, housing, official matters, social interaction). Start output DIRECTLY with '[' and end DIRECTLY with ']'. No extra text or markdown.
Example for non-form: { \"id\": 2, \"type\": \"email\", ..., \"prompt_html\": \"<p>Instruction...</p><textarea name='answer[2]' ...></textarea>\", ... }
Example for form: { \"id\": 1, \"type\": \"form\", ..., \"prompt_html\": \"<p>Instruction...</p><form><label>...</label><input ...><br>...</form>\", ... }
";

// --- استدعاء Gemini API ---
$raw_response = call_gemini_api($prompt, GEMINI_API_KEY, GEMINI_API_URL_GENERATE);

// --- التحقق من استجابة Gemini قبل المتابعة ---
if ($raw_response === false) {
    // الدالة call_gemini_api يجب أن تكون قد سجلت الخطأ في السجلات
    die("خطأ: فشل الاتصال أو الحصول على استجابة صالحة من Gemini API. يرجى مراجعة سجلات أخطاء الخادم.");
}
if (strpos($raw_response, "تم حظر الرد من Gemini بسبب:") === 0) {
    die("خطأ: " . htmlspecialchars($raw_response)); // عرض رسالة الحظر
}


// --- التنظيف قبل محاولة التحليل ---
$cleaned_response = trim($raw_response);
// إزالة ```json إذا كانت موجودة
if (strpos($cleaned_response, '```json') === 0) {
    $cleaned_response = substr($cleaned_response, 7);
    if (substr($cleaned_response, -3) === '```') {
        $cleaned_response = substr($cleaned_response, 0, -3);
    }
    $cleaned_response = trim($cleaned_response);
}
// محاولة استخراج ما بين أول '[' وآخر ']'
$start_pos = strpos($cleaned_response, '[');
$end_pos = strrpos($cleaned_response, ']');
if ($start_pos !== false && $end_pos !== false && $end_pos > $start_pos) {
    $json_part = substr($cleaned_response, $start_pos, $end_pos - $start_pos + 1);
} else {
    // إذا لم تبدأ الاستجابة بـ '['، قد تكون رسالة خطأ من Gemini أو نص غير متوقع
     if (strlen($cleaned_response) < 200) { // افتراض أن الـ JSON الصحيح أطول من ذلك
        die("خطأ: استجابة غير متوقعة أو غير كاملة من Gemini (لا تبدو كـ JSON): <pre>" . htmlspecialchars($cleaned_response) . "</pre>");
     }
    $json_part = $cleaned_response; // حاول تحليلها كما هي على أي حال
}

// --- محاولة تحليل الجزء الذي نعتقد أنه JSON ---
$generated_data = json_decode($json_part, true);

// --- التحقق المحسّن ---
if (json_last_error() === JSON_ERROR_NONE && is_array($generated_data) && count($generated_data) === 12) {
    // نجاح التحليل وعدد الأسئلة صحيح
    $_SESSION['exam_questions'] = $generated_data;
    // تهيئة مصفوفات الإجابات والنقاط والتقييمات باستخدام IDs الصحيحة
    $question_ids = array_map(function($q) { return $q['id']; }, $generated_data);
    if (empty($question_ids)) {
         die("خطأ: تم تحليل JSON بنجاح ولكن لم يتم العثور على IDs للأسئلة.");
    }
    $_SESSION['user_answers'] = array_fill_keys($question_ids, '');
    $_SESSION['scores']       = array_fill_keys($question_ids, null);
    $_SESSION['feedback']     = array_fill_keys($question_ids, '');
    $_SESSION['current_question'] = $question_ids[0]; // تعيين السؤال الأول

    // إعادة التوجيه إلى صفحة الامتحان
    header('Location: exam.php?q=' . $question_ids[0]);
    exit; // مهم جداً: إيقاف التنفيذ بعد إعادة التوجيه

} else {
    // فشل التحليل أو عدد الأسئلة غير صحيح
    $json_error_message = json_last_error_msg();
    // عرض خطأ أكثر تفصيلاً للمساعدة في التشخيص
    die("خطأ: فشل في تحليل استجابة Gemini كـ JSON صالح يحتوي على 12 سؤالاً. <br>"
      . "رسالة خطأ JSON: " . htmlspecialchars($json_error_message) . "<br>"
      . "الجزء الذي تمت محاولة تحليله: <pre>" . htmlspecialchars($json_part) . "</pre><br>"
      . "الاستجابة الخام الأصلية من Gemini (أول 500 حرف): <pre>" . htmlspecialchars(substr($raw_response, 0, 500)) . "...</pre>");
}
?>