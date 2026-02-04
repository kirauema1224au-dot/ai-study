from pathlib import Path
p=Path('api/study/requirements.html')
txt=p.read_text(encoding='utf-8')
old = """genQuestion.innerHTML = body.questions.map((q, idx) => {
            const choices = (q.choices || []).map(c => `<li>${c.choice_label}: ${c.choice_text}</li>`).join('');
            const answerBtns = (q.choices || []).map(c => `<button type=\"button\" class=\"answer-btn\" data-label=\"${c.choice_label}\">${c.choice_label}</button>`).join('');
            return `
              <div class=\"answer-card\" style=\"margin-bottom:14px; padding:12px; border:1px solid var(--border); border-radius:12px; background: rgba(255,255,255,0.02);\">
                <div style=\"margin-bottom:6px;font-weight:700;\">#${q.question_id || idx + 1} ${q.title || ''}</div>
                <div style=\"margin-bottom:6px;\">${q.domain_name || ''} / ${q.topic_name || ''}</div>
                <div style=\"margin-bottom:6px;\">${q.stem || ''}</div>
                <ul style=\"margin:0; padding-left:18px; color:${muted};\">
                  ${choices}
                </ul>
                <div class=\"answer-box\" data-correct=\"${q.correct_label || ''}\" data-qid=\"${q.question_id || ''}\">
                  <div class=\"answer-row\">
                    <span style=\"font-weight:700;\">解答</span>
                    <div class=\"answer-row\" style=\"gap:6px;\">
                      ${answerBtns}
                    </div>
                    <button type=\"button\" class=\"reveal-btn\" data-reveal>正解を表示</button>
                  </div>
                  <div class=\"answer-status\" data-status>解答を選択してください</div>
                </div>
              </div>
            `;
          }).join('');

          """
new = """genQuestion.innerHTML = body.questions.map((q, idx) => {
            const choices = (q.choices || []).map(c => `<li>${c.choice_label}: ${c.choice_text}</li>`).join('');
            const answerBtns = (q.choices || []).map(c => `<button type=\"button\" class=\"answer-btn\" data-label=\"${c.choice_label}\">${c.choice_label}</button>`).join('');
            const explanation = q.explanation || '解説は登録されていません';
            return `
              <div class=\"answer-card\" style=\"margin-bottom:14px; padding:12px; border:1px solid var(--border); border-radius:12px; background: rgba(255,255,255,0.02);\">
                <div style=\"margin-bottom:6px;font-weight:700;\">#${q.question_id || idx + 1} ${q.title || ''}</div>
                <div style=\"margin-bottom:6px;\">${q.domain_name || ''} / ${q.topic_name || ''}</div>
                <div style=\"margin-bottom:6px;\">${q.stem || ''}</div>
                <ul style=\"margin:0; padding-left:18px; color:${muted};\">
                  ${choices}
                </ul>
                <div class=\"answer-box\" data-correct=\"${q.correct_label || ''}\" data-qid=\"${q.question_id || ''}\">
                  <div class=\"answer-row\">
                    <span style=\"font-weight:700;\">解答</span>
                    <div class=\"answer-row\" style=\"gap:6px;\">
                      ${answerBtns}
                    </div>
                    <button type=\"button\" class=\"reveal-btn\" data-reveal>正解を表示</button>
                  </div>
                  <div class=\"answer-status\" data-status>解答を選択してください</div>
                  <div class=\"answer-expl\" data-expl style=\"margin-top:6px; display:none; color:${muted};\">解説: ${explanation}</div>
                </div>
              </div>
            `;
          }).join('');

          """
if old not in txt:
    raise SystemExit('block not found')
p.write_text(txt.replace(old,new,1), encoding='utf-8')
