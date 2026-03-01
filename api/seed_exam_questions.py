import mysql.connector
from datetime import datetime
import json

# DB connection config matching docker setup
db_config = {
    'host': '127.0.0.1',
    'port': 3306,
    'user': 'appuser',
    'password': 'apppass',
    'database': 'appdb'
}

questions_data = [
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "ストラテジ系（企業活動・法務・経営戦略）",
        "title": "BPOの目的",
        "stem": "企業がBPO (Business Process Outsourcing) を導入する主な目的として、最も適切なものはどれか。",
        "explanation": "BPOは、自社の業務プロセスの一部を継続的に外部の専門業者に委託することです。自社のリソースをコアビジネスに集中させ、業務の効率化やコスト削減を図るのが主な目的です。",
        "correct_label": "C",
        "difficulty": 3,
        "choices": [
            {"label": "A", "text": "自社システムの開発を一時的に外部のプログラマに委託する。"},
            {"label": "B", "text": "複数の企業が共同で一つの事業に出資し、新しい会社を設立する。"},
            {"label": "C", "text": "自社の業務プロセスの一部（給与計算やコールセンターなど）を継続的に外部の専門業者に委託し、自社は中核業務に専念する。"},
            {"label": "D", "text": "自社が保有する特許などの知的財産権を他社にライセンス供与し、ロイヤリティを得る。"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "ストラテジ系（企業活動・法務・経営戦略）",
        "title": "著作権の保護対象",
        "stem": "著作権法によって保護される対象として、適切なものはどれか。",
        "explanation": "著作権法は、「思想又は感情を創作的に表現したものであつて、文芸、学術、美術又は音楽の範囲に属するもの（プログラムを含む）」を保護します。アルゴリズムやプログラミング言語それ自体は保護の対象外です。",
        "correct_label": "B",
        "difficulty": 2,
        "choices": [
            {"label": "A", "text": "プログラミング言語"},
            {"label": "B", "text": "ソースコード"},
            {"label": "C", "text": "アルゴリズム"},
            {"label": "D", "text": "通信プロトコル"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "マネジメント系（システム開発・サービス管理）",
        "title": "アジャイル開発の特徴",
        "stem": "システム開発手法の一つであるアジャイル開発の特徴として、最も適切なものはどれか。",
        "explanation": "アジャイル開発は、短い期間（スプリントやイテレーションと呼ばれる）で開発とリリースを繰り返し、環境変化や要求の変更に柔軟に対応する開発手法です。",
        "correct_label": "A",
        "difficulty": 3,
        "choices": [
            {"label": "A", "text": "短期間で実装とテストを繰り返し、要求の変化に柔軟に対応する手法である。"},
            {"label": "B", "text": "要件定義からテストまでの一連の工程を、順番に一度だけ行う手法である。"},
            {"label": "C", "text": "ユーザインタフェースの設計において、エンドユーザが参加できない手法である。"},
            {"label": "D", "text": "大規模なシステム開発においてのみ採用される手法である。"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "マネジメント系（システム開発・サービス管理）",
        "title": "SLAの説明",
        "stem": "ITサービスマネジメントにおけるSLA (Service Level Agreement) の説明として、適切なものはどれか。",
        "explanation": "SLAは、サービス提供者と顧客との間で結ばれる、提供するITサービスの品質や内容に関する合意（契約）のことです。稼働率やサポー卜対応時間などが定義されます。",
        "correct_label": "D",
        "difficulty": 2,
        "choices": [
            {"label": "A", "text": "システムの開発から廃棄までのライフサイクル全般を管理する手法"},
            {"label": "B", "text": "ソフトウェアの不具合を修正し、継続的に改善を行うプロセス"},
            {"label": "C", "text": "経営陣がIT投資の効果を定量的に評価するための指標"},
            {"label": "D", "text": "提供するITサービスの品質について、サービス提供者と顧客との間で合意した文書"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "テクノロジー系（基礎理論・アルゴリズム）",
        "title": "2進数から10進数への変換",
        "stem": "2進数の「1011」を10進数で表現したものはどれか。",
        "explanation": "2進数の各桁は右から順に、2^0=1, 2^1=2, 2^2=4, 2^3=8の重みを持ちます。\n1011 = (1×8) + (0×4) + (1×2) + (1×1) = 8 + 0 + 2 + 1 = 11 となります。",
        "correct_label": "C",
        "difficulty": 1,
        "choices": [
            {"label": "A", "text": "9"},
            {"label": "B", "text": "10"},
            {"label": "C", "text": "11"},
            {"label": "D", "text": "12"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "テクノロジー系（基礎理論・アルゴリズム）",
        "title": "スタックとキュー",
        "stem": "データ構造の一つである「スタック」の特徴として、適切なものはどれか。",
        "explanation": "スタックは「後入れ先出し（LIFO: Last In First Out）」のデータ構造です。最後に挿入されたデータが最初に取り出されます。一方、「先入れ先出し（FIFO）」はキューの特徴です。",
        "correct_label": "B",
        "difficulty": 2,
        "choices": [
            {"label": "A", "text": "最初に格納したデータから順に取り出す方式 (FIFO) である。"},
            {"label": "B", "text": "最後に格納したデータから順に取り出す方式 (LIFO) である。"},
            {"label": "C", "text": "キーとなる値を用いて、データを高速に検索する方式である。"},
            {"label": "D", "text": "データが階層状（木構造）に格納される方式である。"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "システム構成要素・ネットワーク・DB",
        "title": "RAID1の特徴",
        "stem": "ハードディスクの信頼性を高める技術であるRAID 1（ミラーリング）の特徴として、適切なものはどれか。",
        "explanation": "RAID 1は2台以上のハードディスクに全く同じデータを書き込む技術（ミラーリング）です。1台が故障しても残りのディスクでデータを保持できるため高い信頼性を持ちますが、利用可能な容量はディスク全体の半分（2台構成の場合）になります。",
        "correct_label": "A",
        "difficulty": 3,
        "choices": [
            {"label": "A", "text": "複数の磁気ディスクに同じデータを書き込むことで、ディスク故障時のデータ消失を防ぐ。"},
            {"label": "B", "text": "データをブロック単位に分割し、複数の磁気ディスクに分散して書き込むことでアクセス速度を向上させるが、冗長性はない。"},
            {"label": "C", "text": "データとパリティ（誤り訂正符号）を複数の磁気ディスクに分散して記録する。"},
            {"label": "D", "text": "使用頻度の高いデータを高速な半導体メモリに一時的に保存し、アクセスを高速化する。"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "システム構成要素・ネットワーク・DB",
        "title": "MACアドレスの役割",
        "stem": "ネットワーク機器において、MACアドレスが果たす役割として最も適切なものはどれか。",
        "explanation": "MACアドレスは、LANカード（ネットワークインタフェースカード）などのネットワーク機器に製造段階で割り当てられる一意の物理的な識別番号です。これに対して、IPアドレスはネットワーク上で論理的に割り当てられるアドレスです。",
        "correct_label": "C",
        "difficulty": 2,
        "choices": [
            {"label": "A", "text": "インターネット上のドメイン名とIPアドレスを変換する。"},
            {"label": "B", "text": "ネットワーク上のルーティング経路を決定するために使用される論理的なアドレスである。"},
            {"label": "C", "text": "ネットワーク機器（LANカードなど）を物理的に識別するための固有の番号である。"},
            {"label": "D", "text": "Webブラウザが通信を暗号化する際に使用する公開鍵である。"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "システム構成要素・ネットワーク・DB",
        "title": "関係データベースの主キー",
        "stem": "関係データベースのテーブルにおいて、「主キー」が満たすべき条件として適切なものはどれか。",
        "explanation": "主キー（Primary Key）は、テーブル内の行（レコード）を一意に識別するための列です。主キーには重複する値を入れることができず（一意性）、空の値（NULL値）であってもいけません。",
        "correct_label": "D",
        "difficulty": 3,
        "choices": [
            {"label": "A", "text": "値が他のテーブルの列に必ず存在しなければならない。"},
            {"label": "B", "text": "数値型のデータしか設定することができない。"},
            {"label": "C", "text": "重複した値を持つことができるが、Null値（空）は許可されない。"},
            {"label": "D", "text": "重複した値を持つことができず、かつNull値（空）も許可されない。"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "セキュリティ基礎",
        "title": "マルウェアの種類",
        "stem": "利用者の意図に反して、コンピュータに害を及ぼすソフトウェアの総称として適切なものはどれか。",
        "explanation": "コンピュータウイルス、ワーム、トロイの木馬、スパイウェアなど、悪意を持って作成されたソフトウェアの総称をマルウェア (Malware) と呼びます。",
        "correct_label": "A",
        "difficulty": 1,
        "choices": [
            {"label": "A", "text": "マルウェア"},
            {"label": "B", "text": "ファームウェア"},
            {"label": "C", "text": "シェアウェア"},
            {"label": "D", "text": "ミドルウェア"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "セキュリティ基礎",
        "title": "公開鍵暗号方式",
        "stem": "公開鍵暗号方式を用いた通信において、暗号化と復号に用いる鍵の組み合わせとして適切なものはどれか。",
        "explanation": "公開鍵暗号方式では、送信者は「受信者の公開鍵」を使ってデータを暗号化します。暗号化されたデータは、「受信者の秘密鍵」を使わなければ復号できません。これにより、通信経路上での盗聴を防ぎます。",
        "correct_label": "B",
        "difficulty": 4,
        "choices": [
            {"label": "A", "text": "送信データの暗号化には「送信者の秘密鍵」、復号には「送信者の公開鍵」を用いる。"},
            {"label": "B", "text": "送信データの暗号化には「受信者の公開鍵」、復号には「受信者の秘密鍵」を用いる。"},
            {"label": "C", "text": "通信当事者間で事前に共有した一つの「共通鍵」を用いて暗号化と復号を行う。"},
            {"label": "D", "text": "送信データの暗号化には「受信者の秘密鍵」、復号には「受信者の公開鍵」を用いる。"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "セキュリティ基礎",
        "title": "ソーシャルエンジニアリング",
        "stem": "セキュリティ上の脅威である「ソーシャルエンジニアリング」の手法に該当するものはどれか。",
        "explanation": "ソーシャルエンジニアリングとは、情報通信技術を使わずに、人間の心理的な隙や行動のミスにつけ込んで機密情報を盗み出す手法です。肩越しにパスワードを盗み見る（ショルダーハック）や、ゴミ箱を漁る（トラッシュマカク）などが該当します。",
        "correct_label": "C",
        "difficulty": 2,
        "choices": [
            {"label": "A", "text": "OSの脆弱性を突いて、サーバに不正侵入する。"},
            {"label": "B", "text": "ネットワーク上の通信データをツールを使って盗聴する。"},
            {"label": "C", "text": "社員を装って電話をかけ、パスワードを聞き出す。"},
            {"label": "D", "text": "大量のデータをサーバに送りつけ、サービスを停止させる。"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "ストラテジ系（企業活動・法務・経営戦略）",
        "title": "SWOT分析",
        "stem": "SWOT分析の4つの要素として、正しい組み合わせはどれか。",
        "explanation": "SWOT分析は、企業の内部環境である「強み (Strengths)」「弱み (Weaknesses)」と、外部環境である「機会 (Opportunities)」「脅威 (Threats)」の4つの軸で現状を分析する経営戦略手法です。",
        "correct_label": "A",
        "difficulty": 2,
        "choices": [
            {"label": "A", "text": "強み、弱み、機会、脅威"},
            {"label": "B", "text": "戦略、戦術、運用、保守"},
            {"label": "C", "text": "顧客、競合、自社、市場"},
            {"label": "D", "text": "計画、実行、評価、改善"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "ストラテジ系（企業活動・法務・経営戦略）",
        "title": "コーポレートガバナンス",
        "stem": "コーポレートガバナンス（企業統治）の目的として、最も適切なものはどれか。",
        "explanation": "コーポレートガバナンスは、企業経営を監視・規律する仕組みのことです。株主などのステークホルダーの利益を損なわないよう、経営の透明性や公正性を確保し、企業の不祥事を防ぐことを目的としています。",
        "correct_label": "C",
        "difficulty": 3,
        "choices": [
            {"label": "A", "text": "自社のシステムのセキュリティ対策を強化し、サイバー攻撃を防ぐこと。"},
            {"label": "B", "text": "従業員のモチベーションを向上させるため、評価制度を見直すこと。"},
            {"label": "C", "text": "株主などの利益を保護するため、経営の透明性や公正性を確保する仕組みを構築すること。"},
            {"label": "D", "text": "競合他社を買収し、市場シェアを拡大すること。"}
        ]
    },
    {
         "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "マネジメント系（システム開発・サービス管理）",
        "title": "WBSの目的",
        "stem": "プロジェクト管理において、WBS (Work Breakdown Structure) を作成する主な目的はどれか。",
        "explanation": "WBSは、プロジェクトで実施すべき作業を階層的に分解し、全体像を漏れなく把握するための図表です。これにより、作業のスコープや必要なリソース、スケジュールの見積もりが可能になります。",
        "correct_label": "B",
        "difficulty": 3,
        "choices": [
            {"label": "A", "text": "プログラムの内部論理を視覚的に表現するため。"},
            {"label": "B", "text": "プロジェクト全体の作業を細かく分解し、必要なタスクや成果物を明確にするため。"},
            {"label": "C", "text": "システムの要件を定義し、ユーザーとの合意形成を図るため。"},
            {"label": "D", "text": "提供するITサービスの品質目標を数値で設定するため。"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "テクノロジー系（基礎理論・アルゴリズム）",
        "title": "IoTの特徴",
        "stem": "IoT (Internet of Things) の概念を説明したものとして、適切なものはどれか。",
        "explanation": "IoT（モノのインターネット）は、パソコンやスマートフォンだけでなく、家電、自動車、センサーなど様々な「モノ」がインターネットに接続され、相互に情報をやり取りする仕組みのことです。",
        "correct_label": "D",
        "difficulty": 1,
        "choices": [
            {"label": "A", "text": "コンピュータ内に仮想的なハードウェアを構築し、複数のOSを同時に動作させる技術。"},
            {"label": "B", "text": "人間の脳の神経回路の仕組みを模倣し、コンピュータに学習能力を持たせる技術。"},
            {"label": "C", "text": "企業間で商取引のデータを専用のネットワークを用いて電子的に交換する仕組み。"},
            {"label": "D", "text": "家電機器や自動車など、情報通信機器以外の様々なモノがインターネットに接続される仕組み。"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "テクノロジー系（基礎理論・アルゴリズム）",
        "title": "OSS（オープンソースソフトウェア）",
        "stem": "OSS (Open Source Software) の一般的な特徴として、適切なものはどれか。",
        "explanation": "OSSはソースコードが公開されており、誰でもそのソフトウェアの利用、複製、改変、再配布などが許可されているソフトウェアです。代表例としてLinuxなどがあります。ただし、著作権が放棄されているわけではありません。",
        "correct_label": "C",
        "difficulty": 2,
        "choices": [
            {"label": "A", "text": "利用は無料であるが、ソースコードの改変や再配布は一切禁止されている。"},
            {"label": "B", "text": "ソースコードは誰でも閲覧できるが、商用目的での利用はできない。"},
            {"label": "C", "text": "ソースコードが公開されており、利用・改変・再配布がライセンス条件の範囲内で許可されている。"},
            {"label": "D", "text": "開発者が著作権を完全に放棄したソフトウェアである。"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "システム構成要素・ネットワーク・DB",
        "title": "SQLの役割",
        "stem": "関係データベースにおいて、SQLが主に利用される目的はどれか。",
        "explanation": "SQL (Structured Query Language) は、関係データベース管理システム (RDBMS) において、データの検索（SELECT）、挿入（INSERT）、更新（UPDATE）、削除（DELETE）や、テーブルの定義などを行うための言語です。",
        "correct_label": "B",
        "difficulty": 2,
        "choices": [
            {"label": "A", "text": "Webブラウザ上で動作する動的なアニメーションを作成するため。"},
            {"label": "B", "text": "関係データベースのデータの検索、更新、削除、およびテーブルの定義を行うため。"},
            {"label": "C", "text": "ネットワーク上の不正なアクセスを監視し、遮断するため。"},
            {"label": "D", "text": "サーバのオペレーティングシステムを設定し、管理するため。"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "セキュリティ基礎",
        "title": "フィッシング詐欺",
        "stem": "フィッシング（Phishing）の手口として、最も適切なものはどれか。",
        "explanation": "フィッシング詐欺は、実在する金融機関や有名企業などを装った偽の電子メールを送信し、本物そっくりの偽サイト（フィッシングサイト）に誘導して、暗証番号やクレジットカード番号などを入力させて盗み取る手口です。",
        "correct_label": "D",
        "difficulty": 1,
        "choices": [
            {"label": "A", "text": "他人のコンピュータに不正に侵入し、データを破壊する。"},
            {"label": "B", "text": "キーボードの入力履歴を記録するソフトを仕掛け、パスワードを盗む。"},
            {"label": "C", "text": "Webサイトの入力フォームに不正な文字列を入力し、データベースを操作する。"},
            {"label": "D", "text": "金融機関などを装った偽のメールを送信し、偽サイトに誘導して個人情報を入力させる。"}
        ]
    },
    {
        "domain_name": "国家試験（情報処理技術者）",
        "exam_name": "ITパスポート",
        "topic_name": "セキュリティ基礎",
        "title": "生体認証（バイオメトリクス認証）",
        "stem": "生体認証（バイオメトリクス認証）に用いられる情報として、不適切なものはどれか。",
        "explanation": "生体認証は、人間の身体的特徴（指紋、静脈、虹彩、顔など）や行動的特徴（筆跡、キーストロークなど）を用いて個人を識別する認証方式です。パスワードは「記憶に基づく認証」であり、生体情報ではありません。",
        "correct_label": "B",
        "difficulty": 1,
        "choices": [
            {"label": "A", "text": "指紋パターンの特徴"},
            {"label": "B", "text": "利用者が設定した文字列（パスワード）"},
            {"label": "C", "text": "手のひらや指の静脈のパターン"},
            {"label": "D", "text": "瞳の虹彩の模様"}
        ]
    }
]

def seed_db():
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()
        
        q_count = 0
        c_count = 0
        
        for q in questions_data:
            sql_q = """
                INSERT INTO questions 
                (domain_name, exam_name, topic_name, title, stem, explanation, correct_label, difficulty, is_active)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            val_q = (
                q['domain_name'],
                q['exam_name'],
                q['topic_name'],
                q['title'],
                q['stem'],
                q['explanation'],
                q['correct_label'],
                q['difficulty'],
                1
            )
            cursor.execute(sql_q, val_q)
            q_id = cursor.lastrowid
            q_count += 1
            
            sql_c = """
                INSERT INTO question_choices (question_id, choice_label, choice_text)
                VALUES (%s, %s, %s)
            """
            for choice in q['choices']:
                val_c = (q_id, choice['label'], choice['text'])
                cursor.execute(sql_c, val_c)
                c_count += 1
                
        conn.commit()
        print(f"Successfully seeded {q_count} questions and {c_count} choices.")
        
    except mysql.connector.Error as err:
        print(f"Error: {err}")
    finally:
        if 'conn' in locals() and conn.is_connected():
            cursor.close()
            conn.close()

if __name__ == "__main__":
    seed_db()
