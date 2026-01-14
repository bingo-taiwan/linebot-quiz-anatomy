# -*- coding: utf-8 -*-
"""
題庫審計腳本 - 自動檢測常見錯誤
用法: python audit_quiz.py [quiz_dir]
"""

import json
import os
import re
import sys
from collections import Counter
from pathlib import Path

# Windows 終端 UTF-8 支援
if sys.platform == 'win32':
    sys.stdout.reconfigure(encoding='utf-8')

# 簡化輸出（移除顏色代碼以相容 Windows）
def color(text, c=None):
    return str(text)

# 需要圖片的關鍵字
IMAGE_KEYWORDS = ['上圖', '下圖', '圖中', '看圖', '圖片', '圖表', '圖示', '觀察圖', '如圖']

# 無意義選項
MEANINGLESS_OPTIONS = [
    {'A': 'A', 'B': 'B', 'C': 'C', 'D': 'D'},
    {'A': '選項A', 'B': '選項B', 'C': '選項C', 'D': '選項D'},
]

def audit_quiz_pair(quiz_path, answers_path):
    """審計一對題庫和答案檔案"""
    issues = []

    # 讀取檔案
    try:
        with open(quiz_path, 'r', encoding='utf-8') as f:
            quiz_data = json.load(f)
        with open(answers_path, 'r', encoding='utf-8') as f:
            answers_data = json.load(f)
    except Exception as e:
        return [{'type': 'file_error', 'detail': str(e)}]

    questions = {q['id']: q for q in quiz_data.get('questions', [])}
    answers = {a['id']: a for a in answers_data.get('answers', [])}

    # 收集所有圖片 URL 來檢測重複
    all_question_images = []
    all_explanation_images = []

    for qid, q in questions.items():
        question_text = q.get('question', '')
        question_image = q.get('question_image')
        options = q.get('options', {})

        # 答案資料
        ans = answers.get(qid, {})
        explanation_image = ans.get('explanation_image')

        # 檢查 1：題目提到圖但沒有圖片
        needs_image = any(kw in question_text for kw in IMAGE_KEYWORDS)
        if needs_image and not question_image:
            issues.append({
                'id': qid,
                'type': 'missing_question_image',
                'detail': f'題目提到「{[kw for kw in IMAGE_KEYWORDS if kw in question_text][0]}」但 question_image 為空'
            })

        # 檢查 2：無意義選項
        if options in MEANINGLESS_OPTIONS:
            issues.append({
                'id': qid,
                'type': 'meaningless_options',
                'detail': '選項只有 A/B/C/D，沒有實際內容'
            })

        # 檢查 3：圖片 URL 與題號不匹配
        if question_image:
            all_question_images.append((qid, question_image))
            # 檢查檔名中的題號
            match = re.search(r'-[qa](\d+)-', question_image)
            if match:
                img_id = int(match.group(1))
                if img_id != qid:
                    issues.append({
                        'id': qid,
                        'type': 'question_image_id_mismatch',
                        'detail': f'圖片檔名含 a{img_id} 但用在第 {qid} 題'
                    })

        if explanation_image:
            all_explanation_images.append((qid, explanation_image))
            # 檢查檔名中的題號
            match = re.search(r'-[qa](\d+)-', explanation_image)
            if match:
                img_id = int(match.group(1))
                if img_id != qid:
                    issues.append({
                        'id': qid,
                        'type': 'explanation_image_id_mismatch',
                        'detail': f'答案圖片檔名含 a{img_id} 但用在第 {qid} 題'
                    })

    # 檢查 4：重複的圖片 URL（不同題目用同一張圖）
    question_img_urls = [url for _, url in all_question_images]
    explanation_img_urls = [url for _, url in all_explanation_images]

    for url, count in Counter(question_img_urls).items():
        if count > 1:
            dup_ids = [qid for qid, u in all_question_images if u == url]
            issues.append({
                'id': dup_ids,
                'type': 'duplicate_question_image',
                'detail': f'題目 {dup_ids} 使用相同的 question_image'
            })

    for url, count in Counter(explanation_img_urls).items():
        if count > 1:
            dup_ids = [qid for qid, u in all_explanation_images if u == url]
            issues.append({
                'id': dup_ids,
                'type': 'duplicate_explanation_image',
                'detail': f'題目 {dup_ids} 使用相同的 explanation_image'
            })

    # 檢查 5：題目數量與答案數量不一致
    if len(questions) != len(answers):
        issues.append({
            'id': None,
            'type': 'count_mismatch',
            'detail': f'題目 {len(questions)} 題，答案 {len(answers)} 題'
        })

    # 檢查 6：題目和答案的 ID 不對應
    missing_answers = set(questions.keys()) - set(answers.keys())
    extra_answers = set(answers.keys()) - set(questions.keys())
    if missing_answers:
        issues.append({
            'id': list(missing_answers),
            'type': 'missing_answers',
            'detail': f'題目 {list(missing_answers)} 缺少答案'
        })
    if extra_answers:
        issues.append({
            'id': list(extra_answers),
            'type': 'extra_answers',
            'detail': f'答案 {list(extra_answers)} 沒有對應題目'
        })

    return issues

def main():
    # 預設目錄
    if len(sys.argv) > 1:
        quiz_dir = Path(sys.argv[1])
    else:
        quiz_dir = Path(__file__).parent.parent / 'quiz'

    if not quiz_dir.exists():
        print(f"目錄不存在: {quiz_dir}")
        sys.exit(1)

    print(f"\n{'='*60}")
    print(f"  題庫審計報告")
    print(f"  目錄: {quiz_dir}")
    print(f"{'='*60}\n")

    total_issues = 0
    total_files = 0

    # 遍歷所有科目
    for subject_dir in sorted(quiz_dir.iterdir()):
        if not subject_dir.is_dir():
            continue

        print(f"\n[{subject_dir.name}]")
        print("-" * 40)

        # 找出所有 quiz 檔案
        quiz_files = sorted(subject_dir.glob('*-quiz.json'))

        for quiz_file in quiz_files:
            answers_file = quiz_file.with_name(quiz_file.name.replace('-quiz.json', '-answers.json'))

            if not answers_file.exists():
                print(f"  [!]  {quiz_file.name}: 缺少答案檔案")
                total_issues += 1
                continue

            issues = audit_quiz_pair(quiz_file, answers_file)
            total_files += 1

            if issues:
                # 過濾重複的 duplicate 問題（只報告一次）
                seen_duplicates = set()
                filtered_issues = []
                for issue in issues:
                    if 'duplicate' in issue['type']:
                        key = (issue['type'], tuple(issue['id']) if isinstance(issue['id'], list) else issue['id'])
                        if key not in seen_duplicates:
                            seen_duplicates.add(key)
                            filtered_issues.append(issue)
                    else:
                        filtered_issues.append(issue)

                print(f"  [X] {quiz_file.stem}: {len(filtered_issues)} 個問題")
                for issue in filtered_issues:
                    id_str = f"Q{issue['id']}" if issue['id'] else ""
                    print(f"     {color(issue['type'], None)}: {issue['detail']}")
                total_issues += len(filtered_issues)
            else:
                print(f"  [OK] {quiz_file.stem}: OK")

    # 總結
    print(f"\n{'='*60}")
    if total_issues == 0:
        print(color(f"  [OK] 全部通過！共檢查 {total_files} 個題庫", None))
    else:
        print(color(f"  [!]  發現 {total_issues} 個問題，共檢查 {total_files} 個題庫", None))
    print(f"{'='*60}\n")

    return 1 if total_issues > 0 else 0

if __name__ == '__main__':
    sys.exit(main())
