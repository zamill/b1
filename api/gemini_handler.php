<?php
// api/gemini_handler.php

// --- التحقق من تفعيل cURL ---
if (!function_exists('curl_init')) {
     // لا تستخدم die هنا لأن هذا الملف قد يتم تضمينه فقط
     error_log("خطأ فادح: امتداد cURL غير مفعل في PHP.");
     // سنعيد false من الدالة أدناه إذا لم يكن cURL مفعلاً
}


function call_gemini_api($prompt_text, $api_key, $api_url) {
    // --- تحقق إضافي: هل cURL متاح؟ ---
    if (!function_exists('curl_init')) {
        error_log("الدالة call_gemini_api: امتداد cURL غير متاح."); // تسجيل الخطأ
        return false; // فشل مبكر إذا لم يكن cURL متاحاً
    }

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt_text]
                ]
            ]
        ],
         'generationConfig' => [
            'temperature' => 0.6,
            'maxOutputTokens' => 2048, // الحد الأقصى لتوكنز الإخراج
            // يمكنك إضافة 'stopSequences' إذا لزم الأمر
        ]
        // يمكنك إضافة 'safetySettings' هنا إذا أردت تخصيص إعدادات الأمان
        // 'safetySettings' => [ ... ]
    ];

    $payload = json_encode($data);
    if ($payload === false) {
        error_log("الدالة call_gemini_api: فشل تحويل البيانات إلى JSON.");
        return false;
    }

    $ch = curl_init(); // تهيئة cURL
    if (!$ch) {
        error_log("الدالة call_gemini_api: فشل تهيئة cURL.");
        return false;
    }

    // تعيين الخيارات
    curl_setopt($ch, CURLOPT_URL, $api_url . '?key=' . $api_key);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // إرجاع الاستجابة كنص
    curl_setopt($ch, CURLOPT_POST, true); // طلب POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload); // بيانات الطلب
    curl_setopt($ch, CURLOPT_HTTPHEADER, [ // ترويسات الطلب
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90); // مهلة الانتظار الكلية بالثواني
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // مهلة محاولة الاتصال بالثواني
    // الخيارات التالية للتحقق من SSL، يفضل تركها مفعلة في الإنتاج
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    // يمكنك تحديد مسار حزمة CA إذا لزم الأمر في بيئتك
    // curl_setopt($ch, CURLOPT_CAINFO, '/path/to/cacert.pem');

    $response = curl_exec($ch); // تنفيذ الطلب
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // الحصول على كود حالة HTTP
    $curl_error = curl_error($ch); // الحصول على أي خطأ من cURL

    curl_close($ch); // إغلاق الاتصال

    // التحقق من نجاح طلب cURL
    if ($response === false) {
        error_log("الدالة call_gemini_api: فشل طلب cURL. خطأ: " . $curl_error . " | كود HTTP: " . $httpcode);
        return false; // فشل الطلب
    }

    // التحقق من كود حالة HTTP
    if ($httpcode != 200) {
         error_log("الدالة call_gemini_api: استجابة غير ناجحة من API. كود HTTP: " . $httpcode . " | الاستجابة: " . $response);
         return false; // فشل الطلب (خطأ من جانب الخادم أو الطلب)
    }

    // محاولة تحليل استجابة JSON
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
         error_log("الدالة call_gemini_api: فشل تحليل استجابة JSON. خطأ JSON: " . json_last_error_msg() . " | الاستجابة الخام (أول 500 حرف): " . substr($response, 0, 500));
         return false; // فشل تحليل JSON
    }

    // التحقق من وجود النص الأساسي في الاستجابة المتوقعة
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        // التحقق من سبب الإنهاء للتأكد من عدم قطعه
        if (isset($result['candidates'][0]['finishReason']) && $result['candidates'][0]['finishReason'] == 'MAX_TOKENS') {
             error_log("الدالة call_gemini_api: الاستجابة تم قطعها بسبب الوصول إلى MAX_TOKENS.");
             // يمكنك إرجاع خطأ محدد أو النص المقطوع، لكن إرجاع false أكثر أمانًا لتجنب JSON غير مكتمل
             return false;
        }
        // إذا كان كل شيء تمام، أرجع النص
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }
    // التحقق من وجود حظر بسبب المحتوى
    elseif(isset($result['promptFeedback']['blockReason'])) {
         $block_reason = $result['promptFeedback']['blockReason'];
         error_log("الدالة call_gemini_api: تم حظر الاستجابة من Gemini. السبب: " . $block_reason);
         // إرجاع رسالة واضحة للمستخدم
         return "تم حظر الرد من Gemini بسبب سياسات الأمان المتعلقة بالمحتوى (" . htmlspecialchars($block_reason) . "). حاول تعديل النص أو طلب نوع آخر من المحتوى.";
    }
    // إذا لم نجد النص المتوقع ولم يكن هناك حظر واضح
    else {
         error_log("الدالة call_gemini_api: تنسيق استجابة Gemini غير متوقع. الاستجابة الكاملة: " . $response);
         return false; // فشل بسبب تنسيق غير متوقع
    }
}

// --- وظيفة لتقييم الإجابة (النسخة المحسنة) ---
function evaluate_answer_with_gemini($question_prompt_html, $evaluation_criteria, $user_answer, $api_key, $api_url, $question_data) {
    $question_text_only = strip_tags($question_prompt_html);
    // تأكد من أن النقاط رقم صحيح أو استخدم قيمة افتراضية
    $points_for_question = isset($question_data['points']) && is_numeric($question_data['points']) ? (int)$question_data['points'] : 0;

    // --- تعديل Prompt التقييم ---
    $prompt = "
    You are an evaluator for a Dutch B1 level writing exam (Staatsexamen Nt2 Programma I).
    Your feedback MUST be in CONCISE ARABIC.

    Original Question Instructions (Context):
    \"" . $question_text_only . "\"

    Evaluation Criteria for B1 level:
    - Task Completion: Did the user address the main point(s)?
    - Key Grammar/Spelling: Focus on 1-2 significant B1 errors (e.g., word order, verb ending, common spelling mistakes). Provide a brief correction.
    - Clarity & Appropriateness: Is the message clear? Is the tone suitable?
    - Specific Criteria for this question: " . implode(", ", $evaluation_criteria) . "

    User's Answer (in Dutch):
    \"" . $user_answer . "\"

    Provide CONCISE feedback in ARABIC focusing ONLY on:
    1. Mention if the main task was completed (Yes/Partially/No). (المهمة: ...)
    2. Point out 1-2 *most important* errors with a short correction/explanation (e.g., 'خطأ هام: [ذكر الخطأ وتصحيحه باختصار]'). If no major errors, state 'لا توجد أخطاء رئيسية'.
    3. Give one brief, actionable tip for improvement related to B1 level or the specific task criteria. (نصيحة: ...)

    Keep the total feedback short and direct. Avoid general praise or lengthy explanations. Start directly with the feedback points using the Arabic labels provided (المهمة:, خطأ هام:, نصيحة:).

    Finally, on a new line, suggest a score based on B1 level performance for this task, formatted EXACTLY as: 'النقاط المقترحة: X/" . $points_for_question . "' where X is the suggested score (an integer). Base this score realistically on B1 standards, allowing for some minor errors without excessive penalty. Ensure X is between 0 and " . $points_for_question . ".
    ";
    // --- نهاية تعديل Prompt ---

    return call_gemini_api($prompt, $api_key, $api_url);
}
?>