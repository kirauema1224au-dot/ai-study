
    const fields = {
      exam: document.getElementById('exam'),
      domain: document.getElementById('domain'),
      topic: document.getElementById('topic'),
      difficulty: document.getElementById('difficulty'),
      count: document.getElementById('count'),
      format: document.getElementById('format'),
      background: document.getElementById('background'),
      notes: document.getElementById('notes'),
    };
    const statusEl = document.getElementById('status');
    const generateBtn = document.getElementById('generate');
    const genWrap = document.getElementById('generated');
    const genQuestion = document.getElementById('gen-question');

    const suggestionData = {
      exam: ['基本情報技術者', '応用情報技術者', '情報処理安全確保支援士', 'AWS認定クラウドプラクティショナー', 'AWS認定ソリューションアーキテクト', 'CCNA', 'LPIC', '模試演習'],
      domain: ['ネットワーク', 'セキュリティ', 'クラウド', 'データベース', 'OS・ミドルウェア', 'ソフトウェア開発', 'マネジメント', 'ストラテジ'],
      topic: ['OSI参照モデル', 'TCP/IP基礎', 'サブネット計算', 'ファイアウォール/IDS/IPS', '暗号/認証', 'AWS基礎サービス', 'コンテナ/Kubernetes', 'SQL最適化', '要件定義', 'プロジェクト管理'],
      background: ['初学者', 'IT基礎あり', '実務経験1-2年', 'ネットワーク経験あり', '開発経験あり', 'セキュリティ未経験', 'クラウド未経験']
    };

    function attachSuggest(input, key) {
      const box = document.querySelector(`.suggest[data-field="${key}"]`);
      const list = suggestionData[key] || [];
      if (!box || list.length === 0) return;

      function renderSuggestions(term = '') {
        const t = term.trim().toLowerCase();
        const filtered = list.filter(v => !t || v.toLowerCase().includes(t)).slice(0, 20);
        if (filtered.length === 0) {
          box.innerHTML = '<div style="padding:10px 12px; color:var(--muted); font-size:13px;">候補なし</div>';
        } else {
          box.innerHTML = filtered.map(v => `<button type="button" data-val="${v}">${v}</button>`).join('');
          box.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('mousedown', (e) => {
              e.preventDefault(); // blurを抑止しつつ値を先にセット
              input.value = btn.dataset.val || '';
              input.dispatchEvent(new Event('input', { bubbles: true }));
              box.classList.remove('visible');
              input.focus();
            });
          });
        }
      }

      input.addEventListener('focus', () => {
        renderSuggestions(input.value);
        box.classList.add('visible');
      });
      input.addEventListener('input', () => {
        renderSuggestions(input.value);
        box.classList.add('visible');
      });
      input.addEventListener('blur', () => {
        setTimeout(() => box.classList.remove('visible'), 250); // 若干猶予を持たせて外側クリックを許容
      });
    }

    attachSuggest(fields.exam, 'exam');
    attachSuggest(fields.domain, 'domain');
    attachSuggest(fields.topic, 'topic');
    attachSuggest(fields.background, 'background');

    generateBtn.addEventListener('click', async () => {
      statusEl.textContent = 'AI生成→保存中...';
      statusEl.classList.remove('ok','err');
      genWrap.style.display = 'none';
      try {
        const requestedCount = Math.max(1, Math.min(parseInt(fields.count.value, 10) || 4, 200));
        const req = {
          genre: fields.domain.value || '',
          problem_type: fields.topic.value || '',
          background: fields.background.value || '',
          output_format: fields.format.value || '',
          constraints: `難易度:${fields.difficulty.value || '未指定'} ${fields.format.value || ''}`,
          notes: fields.notes.value || '',
          count: requestedCount
        };
        const res = await fetch('/index.php?r=/generate', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify(req)
        });
        const body = await res.json();
        if (!res.ok || body.ok === false) {
          throw new Error(JSON.stringify(body));
        }
        statusEl.textContent = `生成・保存に成功しました (${(body.questions || []).length}問)`;
        statusEl.classList.add('ok');
        if (Array.isArray(body.questions) && body.questions.length > 0) {
          const muted = getComputedStyle(document.body).getPropertyValue('--muted');
          genQuestion.innerHTML = body.questions.map((q, idx) => {
            const choices = (q.choices || []).map(c => `<li>${c.choice_label}: ${c.choice_text}</li>`).join('');
            const answerBtns = (q.choices || []).map(c => `<button type="button" class="answer-btn" data-label="${c.choice_label}">${c.choice_label}</button>`).join('');
            return `
              <div class="answer-card" style="margin-bottom:14px; padding:12px; border:1px solid var(--border); border-radius:12px; background: rgba(255,255,255,0.02);">
                <div style="margin-bottom:6px;font-weight:700;">#${q.question_id || idx + 1} ${q.title || ''}</div>
                <div style="margin-bottom:6px;">${q.domain_name || ''} / ${q.topic_name || ''}</div>
                <div style="margin-bottom:6px;">${q.stem || ''}</div>
                <ul style="margin:0; padding-left:18px; color:${muted};">
                  ${choices}
                </ul>
                <div class="answer-box" data-correct="${q.correct_label || ''}" data-qid="${q.question_id || ''}">
                  <div class="answer-row">
                    <span style="font-weight:700;">解答</span>
                    <div class="answer-row" style="gap:6px;">
                      ${answerBtns}
                    </div>
                    <button type="button" class="reveal-btn" data-reveal>正解を表示</button>
                  </div>
                  <div class="answer-status" data-status>解答を選択してください</div>
                </div>
              </div>
            `;
          }).join('');

          genQuestion.querySelectorAll('.answer-box').forEach(answerBox => {
            const statusNode = answerBox.querySelector('[data-status]');
            const revealBtn = answerBox.querySelector('[data-reveal]');
            const correct = answerBox.dataset.correct || '';
            const qid = Number(answerBox.dataset.qid || 0);

            function updateStatus(msg, mode = '') {
              statusNode.textContent = msg;
              statusNode.classList.remove('ok', 'err');
              if (mode) statusNode.classList.add(mode);
            }

            async function submitAnswer(label) {
              if (!qid || !label) return;
              updateStatus('送信中...', '');
              try {
                const res = await fetch('/index.php?r=/answers', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify([{
                    user_id: 1,
                    question_id: qid,
                    selected_label: label,
                    elapsed_ms: null
                  }])
                });
                const body = await res.json();
                if (!res.ok || body.ok === false) {
                  throw new Error(JSON.stringify(body));
                }
                const isCorrect = correct && label === correct;
                updateStatus(isCorrect ? '判定: 正解' : '判定: 不正解', isCorrect ? 'ok' : 'err');
              } catch (e) {
                updateStatus('送信エラー: ' + e.message, 'err');
              }
            }

            answerBox.querySelectorAll('.answer-btn').forEach(btn => {
              btn.addEventListener('click', () => submitAnswer(btn.dataset.label || ''));
            });
            revealBtn.addEventListener('click', () => {
              if (correct) {
                updateStatus(`正解: ${correct}`, 'ok');
              } else {
                updateStatus('正解が登録されていません', 'err');
              }
            });
          });

          genWrap.style.display = 'block';
        }
      } catch (e) {
        statusEl.textContent = 'エラー: ' + e.message;
        statusEl.classList.add('err');
      }
    });
  