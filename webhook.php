<?php
/**
 * LINE Bot 題庫系統 - Webhook 入口
 * 支援 Flex Message + 圖片混合 (v1.2)
 */

// Debug 日誌
function logDebug($msg) {
    file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/wuxing/wuxing.php';

// 取得 LINE 傳來的資料
$content = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';

logDebug('=== Webhook called ===');
logDebug('Content: ' . substr($content, 0, 300));

// 驗證簽名
if (!verifySignature($content, $signature)) {
    logDebug('Invalid signature');
    http_response_code(400);
    exit('Invalid signature');
}

// 解析事件
$events = json_decode($content, true);
if (!isset($events['events'])) {
    http_response_code(200);
    exit('No events');
}

// 處理每個事件
foreach ($events['events'] as $event) {
    if ($event['type'] === 'message' && $event['message']['type'] === 'text') {
        handleTextMessage($event);
    }
}

http_response_code(200);
exit('OK');

// ========== 核心函數 ==========

/**
 * 驗證 LINE 簽名
 */
function verifySignature($content, $signature) {
    $hash = hash_hmac('sha256', $content, LINE_CHANNEL_SECRET, true);
    $expected = base64_encode($hash);
    return hash_equals($expected, $signature);
}

/**
 * 處理文字訊息
 */
function handleTextMessage($event) {
    global $SUBJECTS;

    $userId = $event['source']['userId'];
    $replyToken = $event['replyToken'];
    $text = trim($event['message']['text']);

    // 載入使用者狀態
    $session = loadSession($userId);

    // 根據使用者狀態和輸入處理
    switch (true) {
        // 五行穿衣（今日）
        case in_array($text, ['五行', '穿衣', '五行穿衣', '今日穿衣', '今日顏色', '幸運色']):
            replyWuXing($replyToken);
            break;

        // 五行穿衣（明日）
        case in_array($text, ['明日五行', '明日穿衣', '明日五行穿衣', '明天穿衣', '明日顏色']):
            replyWuXingTomorrow($replyToken);
            break;

        // 開始/主選單
        case in_array($text, ['開始', '主選單', '選單', 'menu', '0']):
            $session = ['state' => 'menu'];
            saveSession($userId, $session);
            replyMainMenu($replyToken);
            break;

        // 數字選擇科目
        case $session['state'] === 'menu' && is_numeric($text):
            $subjectKeys = array_keys($SUBJECTS);
            $index = intval($text) - 1;
            if (isset($subjectKeys[$index])) {
                $subject = $subjectKeys[$index];
                $session['state'] = 'select_chapter';
                $session['subject'] = $subject;
                saveSession($userId, $session);
                replyChapterMenu($replyToken, $subject);
            } else {
                replyText($replyToken, "請輸入有效的數字選項");
            }
            break;

        // 選擇章節開始答題
        case $session['state'] === 'select_chapter':
            // 返回主選單
            if ($text === '0') {
                $session = ['state' => 'menu'];
                saveSession($userId, $session);
                replyMainMenu($replyToken);
                break;
            }

            $subject = $session['subject'];
            $chapters = $SUBJECTS[$subject]['chapters'] ?? [];
            $chapterKeys = array_keys($chapters);

            if (is_numeric($text)) {
                $index = intval($text) - 1;
                $chapter = $chapterKeys[$index] ?? null;
            } else {
                $chapter = isset($chapters[$text]) ? $text : null;
            }

            if ($chapter && file_exists(QUIZ_DIR . "/{$subject}/{$chapter}-quiz.json")) {
                $session['state'] = 'answering';
                $session['chapter'] = $chapter;
                $session['current'] = 0;
                $session['correct'] = 0;
                $session['total'] = 0;
                saveSession($userId, $session);
                sendQuestion($replyToken, $userId);
            } else {
                replyText($replyToken, "找不到該章節題庫，請重新選擇");
            }
            break;

        // 答題中
        case $session['state'] === 'answering':
            $answer = strtoupper($text);

            if (in_array($text, ['結束', '停止', 'quit', 'q'])) {
                showResult($replyToken, $session);
                $session = ['state' => 'menu'];
                saveSession($userId, $session);
                break;
            }

            if (in_array($answer, ['A', 'B', 'C', 'D'])) {
                checkAnswer($replyToken, $userId, $answer);
            } else {
                replyText($replyToken, "請輸入 A、B、C 或 D\n或輸入「結束」查看成績");
            }
            break;

        // 等待下一題
        case $session['state'] === 'waiting_next':
            if (in_array($text, ['下一題', '繼續', 'n', 'next', '1'])) {
                $session['state'] = 'answering';
                saveSession($userId, $session);
                sendQuestion($replyToken, $userId);
            } elseif (in_array($text, ['結束', '停止', 'quit', 'q', '2'])) {
                showResult($replyToken, $session);
                $session = ['state' => 'menu'];
                saveSession($userId, $session);
            } else {
                replyText($replyToken, "請輸入「下一題」繼續\n或輸入「結束」查看成績");
            }
            break;

        // 預設
        default:
            $session = ['state' => 'menu'];
            saveSession($userId, $session);
            replyMainMenu($replyToken);
            break;
    }
}

/**
 * 回覆主選單 (Flex Message)
 */
function replyMainMenu($replyToken) {
    global $SUBJECTS;

    $buttons = [];
    $i = 1;
    foreach ($SUBJECTS as $key => $subject) {
        $chapterCount = count($subject['chapters']);
        $label = $chapterCount > 0
            ? "{$subject['name']} ({$chapterCount}章節)"
            : "{$subject['name']} (準備中)";

        $buttons[] = [
            'type' => 'button',
            'style' => $chapterCount > 0 ? 'primary' : 'secondary',
            'height' => 'sm',
            'action' => [
                'type' => 'message',
                'label' => $label,
                'text' => (string)$i
            ]
        ];
        $i++;
    }

    $flex = [
        'type' => 'flex',
        'altText' => '題庫系統 - 選擇科目',
        'contents' => [
            'type' => 'bubble',
            'size' => 'kilo',
            'header' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '📚 題庫系統',
                        'weight' => 'bold',
                        'size' => 'xl',
                        'color' => '#ffffff'
                    ]
                ],
                'backgroundColor' => '#27ACB2',
                'paddingAll' => '15px'
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '請選擇科目：',
                        'size' => 'md',
                        'color' => '#666666',
                        'margin' => 'md'
                    ]
                ],
                'paddingAll' => '15px'
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'spacing' => 'sm',
                'contents' => $buttons,
                'paddingAll' => '15px'
            ]
        ]
    ];

    replyMessages($replyToken, [$flex]);
}

/**
 * 回覆五行穿衣 (Flex Message) - 今日
 */
function replyWuXing($replyToken) {
    $flex = WuXing::generateFlexMessage();
    replyMessages($replyToken, [$flex]);
}

/**
 * 回覆五行穿衣 (Flex Message) - 明日
 */
function replyWuXingTomorrow($replyToken) {
    $flex = WuXing::generateTomorrowFlexMessage();
    replyMessages($replyToken, [$flex]);
}

/**
 * 回覆章節選單 (Flex Message) - 使用 button 按鈕
 */
function replyChapterMenu($replyToken, $subject) {
    global $SUBJECTS;

    $subjectName = $SUBJECTS[$subject]['name'];
    $chapters = $SUBJECTS[$subject]['chapters'];

    if (empty($chapters)) {
        replyText($replyToken, "{$subjectName} 的題庫準備中，請稍後再來！\n\n輸入「0」回主選單");
        return;
    }

    $boxItems = [];
    $i = 1;
    foreach ($chapters as $key => $name) {
        $quiz = loadQuiz($subject, $key);
        $count = $quiz ? count($quiz['questions']) : 0;

        $boxItems[] = [
            'type' => 'box',
            'layout' => 'horizontal',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => $name,
                    'size' => 'sm',
                    'color' => '#ffffff',
                    'flex' => 4,
                    'wrap' => true,
                    'gravity' => 'center'
                ],
                [
                    'type' => 'text',
                    'text' => "({$count}題)",
                    'size' => 'xs',
                    'color' => '#dddddd',
                    'flex' => 0,
                    'gravity' => 'center',
                    'align' => 'end'
                ]
            ],
            'backgroundColor' => '#4A90D9',
            'cornerRadius' => '8px',
            'paddingAll' => '12px',
            'margin' => 'sm',
            'action' => [
                'type' => 'message',
                'text' => (string)$i
            ]
        ];
        $i++;
    }

    // 返回按鈕
    $boxItems[] = [
        'type' => 'box',
        'layout' => 'horizontal',
        'contents' => [
            [
                'type' => 'text',
                'text' => '↩ 返回主選單',
                'size' => 'sm',
                'color' => '#666666',
                'align' => 'center',
                'gravity' => 'center'
            ]
        ],
        'backgroundColor' => '#E0E0E0',
        'cornerRadius' => '8px',
        'paddingAll' => '12px',
        'margin' => 'md',
        'action' => [
            'type' => 'message',
            'text' => '0'
        ]
    ];

    $flex = [
        'type' => 'flex',
        'altText' => "{$subjectName} - 選擇章節",
        'contents' => [
            'type' => 'bubble',
            'size' => 'mega',
            'header' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => "📖 {$subjectName}",
                        'weight' => 'bold',
                        'size' => 'lg',
                        'color' => '#ffffff'
                    ],
                    [
                        'type' => 'text',
                        'text' => '點選章節開始測驗',
                        'size' => 'xs',
                        'color' => '#ffffffaa',
                        'margin' => 'sm'
                    ]
                ],
                'backgroundColor' => '#FF6B6B',
                'paddingAll' => '15px'
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => $boxItems,
                'spacing' => 'none',
                'paddingAll' => '12px'
            ]
        ]
    ];

    replyMessages($replyToken, [$flex]);
}

/**
 * 發送題目 (Flex Message + 圖片)
 */
function sendQuestion($replyToken, $userId) {
    $session = loadSession($userId);
    $quiz = loadQuiz($session['subject'], $session['chapter']);

    if (!$quiz || $session['current'] >= count($quiz['questions'])) {
        showResult($replyToken, $session);
        $session = ['state' => 'menu'];
        saveSession($userId, $session);
        return;
    }

    $q = $quiz['questions'][$session['current']];
    $total = count($quiz['questions']);
    $num = $session['current'] + 1;

    $messages = [];

    // 建立選項按鈕
    $optionButtons = [];
    foreach ($q['options'] as $key => $value) {
        $optionButtons[] = [
            'type' => 'button',
            'style' => 'primary',
            'height' => 'sm',
            'action' => [
                'type' => 'message',
                'label' => "({$key}) {$value}",
                'text' => $key
            ],
            'color' => '#5B8DEF'
        ];
    }

    // 如果有題目圖片
    if (!empty($q['question_image'])) {
        $imageUrl = IMAGE_BASE_URL . '/' . $q['question_image'];

        $flex = [
            'type' => 'flex',
            'altText' => "第 {$num}/{$total} 題",
            'contents' => [
                'type' => 'bubble',
                'size' => 'mega',
                'header' => [
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => "📝 第 {$num}/{$total} 題",
                            'weight' => 'bold',
                            'size' => 'lg',
                            'color' => '#ffffff'
                        ],
                        [
                            'type' => 'text',
                            'text' => "進度 " . round(($num/$total)*100) . "%",
                            'size' => 'sm',
                            'color' => '#ffffff',
                            'align' => 'end'
                        ]
                    ],
                    'backgroundColor' => '#4A90D9',
                    'paddingAll' => '15px'
                ],
                'hero' => [
                    'type' => 'image',
                    'url' => $imageUrl,
                    'size' => 'full',
                    'aspectRatio' => '16:9',
                    'aspectMode' => 'fit'
                ],
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => $q['question'],
                            'wrap' => true,
                            'size' => 'md',
                            'color' => '#333333'
                        ]
                    ],
                    'paddingAll' => '15px'
                ],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'spacing' => 'sm',
                    'contents' => $optionButtons,
                    'paddingAll' => '15px'
                ]
            ]
        ];
    } else {
        // 純文字題目
        $flex = [
            'type' => 'flex',
            'altText' => "第 {$num}/{$total} 題",
            'contents' => [
                'type' => 'bubble',
                'size' => 'mega',
                'header' => [
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => "📝 第 {$num}/{$total} 題",
                            'weight' => 'bold',
                            'size' => 'lg',
                            'color' => '#ffffff'
                        ],
                        [
                            'type' => 'text',
                            'text' => "進度 " . round(($num/$total)*100) . "%",
                            'size' => 'sm',
                            'color' => '#ffffff',
                            'align' => 'end'
                        ]
                    ],
                    'backgroundColor' => '#4A90D9',
                    'paddingAll' => '15px'
                ],
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => $q['question'],
                            'wrap' => true,
                            'size' => 'md',
                            'weight' => 'bold',
                            'color' => '#333333'
                        ]
                    ],
                    'paddingAll' => '15px'
                ],
                'footer' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'spacing' => 'sm',
                    'contents' => $optionButtons,
                    'paddingAll' => '15px'
                ]
            ]
        ];
    }

    $messages[] = $flex;
    replyMessages($replyToken, $messages);
}

/**
 * 檢查答案 (Flex Message)
 */
function checkAnswer($replyToken, $userId, $answer) {
    $session = loadSession($userId);
    $quiz = loadQuiz($session['subject'], $session['chapter']);
    $answersData = loadAnswers($session['subject'], $session['chapter']);

    $currentQ = $session['current'];
    $correctAnswer = $answersData['answers'][$currentQ]['answer'];
    $explanation = $answersData['answers'][$currentQ]['explanation'];
    $explanationImage = $answersData['answers'][$currentQ]['explanation_image'] ?? null;

    $session['total']++;
    $isCorrect = ($answer === $correctAnswer);

    if ($isCorrect) {
        $session['correct']++;
        $headerColor = '#4CAF50';
        $headerText = '✅ 正確！';
        $headerIcon = '🎉';
    } else {
        $headerColor = '#F44336';
        $headerText = '❌ 錯誤';
        $headerIcon = '💡';
    }

    $session['current']++;
    $isLastQuestion = ($session['current'] >= count($quiz['questions']));

    $messages = [];

    // 解析內容
    $bodyContents = [
        [
            'type' => 'text',
            'text' => $isCorrect ? '答對了！' : "正確答案是 ({$correctAnswer})",
            'weight' => 'bold',
            'size' => 'md',
            'color' => $isCorrect ? '#4CAF50' : '#F44336'
        ],
        [
            'type' => 'separator',
            'margin' => 'lg'
        ],
        [
            'type' => 'text',
            'text' => '📖 解析',
            'weight' => 'bold',
            'size' => 'sm',
            'color' => '#666666',
            'margin' => 'lg'
        ],
        [
            'type' => 'text',
            'text' => $explanation,
            'wrap' => true,
            'size' => 'sm',
            'color' => '#333333',
            'margin' => 'sm'
        ]
    ];

    // 下一步按鈕
    if ($isLastQuestion) {
        $footerContents = [
            [
                'type' => 'button',
                'style' => 'primary',
                'action' => [
                    'type' => 'message',
                    'label' => '🏆 查看成績',
                    'text' => '結束'
                ],
                'color' => '#FF9800'
            ]
        ];
        $session = ['state' => 'menu'];
    } else {
        $footerContents = [
            [
                'type' => 'button',
                'style' => 'primary',
                'action' => [
                    'type' => 'message',
                    'label' => '➡️ 下一題',
                    'text' => '下一題'
                ],
                'color' => '#4A90D9'
            ],
            [
                'type' => 'button',
                'style' => 'secondary',
                'action' => [
                    'type' => 'message',
                    'label' => '🏁 結束測驗',
                    'text' => '結束'
                ]
            ]
        ];
        $session['state'] = 'waiting_next';
    }

    // 建立 Flex Message
    $flexContents = [
        'type' => 'bubble',
        'size' => 'mega',
        'header' => [
            'type' => 'box',
            'layout' => 'vertical',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => $headerText,
                    'weight' => 'bold',
                    'size' => 'xl',
                    'color' => '#ffffff'
                ]
            ],
            'backgroundColor' => $headerColor,
            'paddingAll' => '15px'
        ],
        'body' => [
            'type' => 'box',
            'layout' => 'vertical',
            'contents' => $bodyContents,
            'paddingAll' => '15px'
        ],
        'footer' => [
            'type' => 'box',
            'layout' => 'vertical',
            'spacing' => 'sm',
            'contents' => $footerContents,
            'paddingAll' => '15px'
        ]
    ];

    // 如果有解析圖片，加入 hero
    if (!empty($explanationImage)) {
        $flexContents['hero'] = [
            'type' => 'image',
            'url' => IMAGE_BASE_URL . '/' . $explanationImage,
            'size' => 'full',
            'aspectRatio' => '16:9',
            'aspectMode' => 'fit'
        ];
    }

    $messages[] = [
        'type' => 'flex',
        'altText' => $headerText,
        'contents' => $flexContents
    ];

    saveSession($userId, $session);
    replyMessages($replyToken, $messages);
}

/**
 * 顯示成績 (Flex Message)
 */
function showResult($replyToken, $session) {
    global $SUBJECTS;

    $subject = $SUBJECTS[$session['subject']]['name'] ?? '未知';
    $chapter = $SUBJECTS[$session['subject']]['chapters'][$session['chapter']] ?? '未知';

    $correct = $session['correct'] ?? 0;
    $total = $session['total'] ?? 0;
    $percent = $total > 0 ? round(($correct / $total) * 100) : 0;

    // 根據成績選擇顏色和評語
    if ($percent >= 80) {
        $headerColor = '#4CAF50';
        $grade = '優秀 🌟';
        $comment = '太棒了！繼續保持！';
    } elseif ($percent >= 60) {
        $headerColor = '#FF9800';
        $grade = '良好 👍';
        $comment = '不錯喔！再接再厲！';
    } else {
        $headerColor = '#F44336';
        $grade = '加油 💪';
        $comment = '多練習幾次會更好！';
    }

    $flex = [
        'type' => 'flex',
        'altText' => "測驗結果：{$correct}/{$total}",
        'contents' => [
            'type' => 'bubble',
            'size' => 'mega',
            'header' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '📊 測驗結果',
                        'weight' => 'bold',
                        'size' => 'xl',
                        'color' => '#ffffff'
                    ]
                ],
                'backgroundColor' => $headerColor,
                'paddingAll' => '15px'
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => "{$percent}%",
                        'weight' => 'bold',
                        'size' => '3xl',
                        'color' => $headerColor,
                        'align' => 'center'
                    ],
                    [
                        'type' => 'text',
                        'text' => $grade,
                        'size' => 'lg',
                        'color' => '#666666',
                        'align' => 'center',
                        'margin' => 'sm'
                    ],
                    [
                        'type' => 'separator',
                        'margin' => 'lg'
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'horizontal',
                        'contents' => [
                            ['type' => 'text', 'text' => '科目', 'size' => 'sm', 'color' => '#999999', 'flex' => 1],
                            ['type' => 'text', 'text' => $subject, 'size' => 'sm', 'color' => '#333333', 'flex' => 2, 'align' => 'end']
                        ],
                        'margin' => 'lg'
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'horizontal',
                        'contents' => [
                            ['type' => 'text', 'text' => '章節', 'size' => 'sm', 'color' => '#999999', 'flex' => 1],
                            ['type' => 'text', 'text' => $chapter, 'size' => 'sm', 'color' => '#333333', 'flex' => 2, 'align' => 'end']
                        ],
                        'margin' => 'sm'
                    ],
                    [
                        'type' => 'box',
                        'layout' => 'horizontal',
                        'contents' => [
                            ['type' => 'text', 'text' => '答對', 'size' => 'sm', 'color' => '#999999', 'flex' => 1],
                            ['type' => 'text', 'text' => "{$correct} / {$total} 題", 'size' => 'sm', 'color' => '#333333', 'flex' => 2, 'align' => 'end']
                        ],
                        'margin' => 'sm'
                    ],
                    [
                        'type' => 'separator',
                        'margin' => 'lg'
                    ],
                    [
                        'type' => 'text',
                        'text' => $comment,
                        'size' => 'md',
                        'color' => '#666666',
                        'align' => 'center',
                        'margin' => 'lg'
                    ]
                ],
                'paddingAll' => '20px'
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'spacing' => 'sm',
                'contents' => [
                    [
                        'type' => 'button',
                        'style' => 'primary',
                        'action' => [
                            'type' => 'message',
                            'label' => '🔄 再測一次',
                            'text' => '開始'
                        ],
                        'color' => '#27ACB2'
                    ]
                ],
                'paddingAll' => '15px'
            ]
        ]
    ];

    replyMessages($replyToken, [$flex]);
}

// ========== 工具函數 ==========

function loadQuiz($subject, $chapter) {
    $file = QUIZ_DIR . "/{$subject}/{$chapter}-quiz.json";
    if (!file_exists($file)) return null;
    return json_decode(file_get_contents($file), true);
}

function loadAnswers($subject, $chapter) {
    $file = QUIZ_DIR . "/{$subject}/{$chapter}-answers.json";
    if (!file_exists($file)) return null;
    return json_decode(file_get_contents($file), true);
}

function loadSession($userId) {
    if (!file_exists(SESSION_FILE)) {
        return ['state' => 'menu'];
    }
    $sessions = json_decode(file_get_contents(SESSION_FILE), true) ?? [];
    return $sessions[$userId] ?? ['state' => 'menu'];
}

function saveSession($userId, $session) {
    $sessions = [];
    if (file_exists(SESSION_FILE)) {
        $sessions = json_decode(file_get_contents(SESSION_FILE), true) ?? [];
    }
    $sessions[$userId] = $session;
    file_put_contents(SESSION_FILE, json_encode($sessions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function replyText($replyToken, $text) {
    replyMessages($replyToken, [['type' => 'text', 'text' => $text]]);
}

function replyMessages($replyToken, $messages) {
    $messages = array_slice($messages, 0, 5);

    $data = [
        'replyToken' => $replyToken,
        'messages' => $messages
    ];

    logDebug('Sending: ' . json_encode($data, JSON_UNESCAPED_UNICODE));

    $ch = curl_init(LINE_REPLY_API);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    logDebug("LINE API Response (HTTP $httpCode): $response");
    curl_close($ch);
}
