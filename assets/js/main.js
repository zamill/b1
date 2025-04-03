document.addEventListener('DOMContentLoaded', function() {
    const questionContent = document.getElementById('question-content');
    const translationPopup = document.getElementById('translation-popup');
    const checkAnswerBtn = document.getElementById('check-answer-btn');
    const feedbackArea = document.getElementById('feedback-area');
    const feedbackText = document.getElementById('feedback-text');
    const loadingSpinner = document.getElementById('loading-spinner');

    // 1. التعامل مع نقرات الكلمات للترجمة
    if (questionContent && translationPopup) {
        questionContent.addEventListener('click', function(event) {
            if (event.target.classList.contains('translateable')) {
                const word = event.target.textContent.trim().toLowerCase(); // الحصول على الكلمة وتوحيد حالتها

                // البحث عن الترجمة في الكائن المحمل مسبقاً
                const translation = wordTranslations[word] || wordTranslations[event.target.textContent.trim()]; // جرب الكلمة الأصلية أيضاً

                if (translation) {
                    translationPopup.textContent = translation;
                    // تحديد موقع النافذة المنبثقة بجوار الكلمة
                    const rect = event.target.getBoundingClientRect();
                    translationPopup.style.left = `${window.scrollX + rect.left}px`;
                    translationPopup.style.top = `${window.scrollY + rect.bottom + 5}px`; // أسفل الكلمة بقليل
                    translationPopup.style.display = 'block';

                    // إخفاء النافذة المنبثقة عند النقر في أي مكان آخر
                    setTimeout(() => { // تأخير بسيط للسماح بالنقر المزدوج إن وجد
                        document.addEventListener('click', hidePopupOnClickOutside, { once: true });
                    }, 100);

                } else {
                    // (اختياري) يمكنك هنا استدعاء API للترجمة الفورية إذا لم تكن الكلمة موجودة
                    console.warn(`Translation not found for: ${word}`);
                     translationPopup.textContent = 'الترجمة غير متوفرة';
                     const rect = event.target.getBoundingClientRect();
                     translationPopup.style.left = `${window.scrollX + rect.left}px`;
                     translationPopup.style.top = `${window.scrollY + rect.bottom + 5}px`;
                     translationPopup.style.display = 'block';
                     setTimeout(() => { document.addEventListener('click', hidePopupOnClickOutside, { once: true }); }, 100);
                }
            }
        });
    }

    function hidePopupOnClickOutside(event) {
        if (translationPopup && !translationPopup.contains(event.target) && !event.target.classList.contains('translateable')) {
            translationPopup.style.display = 'none';
        } else if (translationPopup && translationPopup.style.display === 'block') {
            // إذا نقر المستخدم على كلمة أخرى قابلة للترجمة، لا تخفي النافذة فوراً،
            // دع معالج النقر الجديد يظهر الترجمة الجديدة
             document.addEventListener('click', hidePopupOnClickOutside, { once: true });
        }
    }

    // 2. التعامل مع زر "تحقق من الإجابة" (AJAX)
    if (checkAnswerBtn && feedbackArea && loadingSpinner) {
        checkAnswerBtn.addEventListener('click', function() {
            const questionId = this.getAttribute('data-question-id');
            // العثور على عنصر الإدخال الخاص بهذا السؤال (نفترض أن له name='answer[ID]')
            const answerInput = questionContent.querySelector(`[name='answer[${questionId}]']`);

            if (!answerInput) {
                console.error('Could not find answer input for question ' + questionId);
                alert('حدث خطأ: لم يتم العثور على حقل الإجابة.');
                return;
            }

            const userAnswer = answerInput.value.trim();

            if (userAnswer === "") {
                alert("يرجى كتابة إجابتك أولاً قبل طلب التقييم.");
                return;
            }

            // إظهار مؤشر التحميل وتعطيل الزر
            loadingSpinner.style.display = 'inline-block';
            checkAnswerBtn.disabled = true;
            feedbackArea.style.display = 'none'; // إخفاء التقييم القديم

            // إرسال البيانات إلى الخادم عبر AJAX (Fetch API)
            fetch('api/check_answer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded', // أو 'application/json' إذا أرسلت JSON
                },
                // إرسال البيانات كـ form data
                body: `question_id=${encodeURIComponent(questionId)}&answer=${encodeURIComponent(userAnswer)}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json(); // نتوقع استجابة JSON
            })
            .then(data => {
                if (data.success && data.feedback) {
                    // عرض التقييم المستلم
                    feedbackText.innerHTML = data.feedback.replace(/\n/g, '<br>'); // استبدال سطور جديدة بـ <br>
                    feedbackArea.style.display = 'block';
                } else {
                    // عرض رسالة خطأ من الخادم
                    feedbackText.textContent = data.error || 'حدث خطأ غير متوقع أثناء الحصول على التقييم.';
                    feedbackArea.style.display = 'block'; // إظهار منطقة التقييم لعرض الخطأ
                }
            })
            .catch(error => {
                console.error('Error fetching feedback:', error);
                feedbackText.textContent = 'فشل الاتصال بالخادم للحصول على التقييم. يرجى المحاولة مرة أخرى. (' + error.message + ')';
                feedbackArea.style.display = 'block';
            })
            .finally(() => {
                // إخفاء مؤشر التحميل وإعادة تفعيل الزر
                loadingSpinner.style.display = 'none';
                checkAnswerBtn.disabled = false;
            });
        });
    }

    // 3. (اختياري) حفظ الإجابة في الجلسة عند التنقل أو تغيير الإجابة
    const answerInputs = questionContent.querySelectorAll('textarea, input[type="text"]'); // أو أي حقول إجابة أخرى
    answerInputs.forEach(input => {
        input.addEventListener('change', () => {
            const answer = input.value;
            const qId = currentQuestionId; // نستخدم ID المحفوظ في JavaScript

            // إرسال تحديث بسيط للخادم لحفظ الإجابة (يمكن تحسينه لتقليل الطلبات)
            fetch('api/save_answer.php', { // ستحتاج لإنشاء هذا الملف
                 method: 'POST',
                 headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                 body: `question_id=${qId}&answer=${encodeURIComponent(answer)}`
            }).then(response => response.json())
              .then(data => { /* console.log('Answer saved:', data) */ })
              .catch(error => console.error('Error saving answer:', error));
        });
    });

});