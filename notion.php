<?php
$notionApiKey = 'YOUR_NOTION_API_KEY';

/**
 * Notion 페이지를 HTML로 변환하여 표시
 * 
 * @param string $pageId 표시할 Notion 페이지 ID
 * @return string HTML 형식으로 변환된 페이지 내용
 */
function getNotionPageAsHtml($pageId) {
    global $notionApiKey;
    
    // Notion API의 페이지 블록 조회 엔드포인트
    $url = "https://api.notion.com/v1/blocks/{$pageId}/children";
    
    // cURL 세션 초기화
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$notionApiKey}",
        "Notion-Version: 2022-06-28",
        "Content-Type: application/json"
    ]);
    
    // API 호출 및 응답 받기
    $response = curl_exec($ch);
    
    if(curl_errno($ch)) {
        return '<p>Error: ' . curl_error($ch) . '</p>';
    }
    
    curl_close($ch);
    
    // 응답 결과를 JSON에서 PHP 배열로 변환
    $blocks = json_decode($response, true);
    
    if (!isset($blocks['results']) || empty($blocks['results'])) {
        return '<p>페이지를 찾을 수 없거나 내용이 없습니다.</p>';
    }
    
    // HTML로 변환
    $html = '<div class="notion-page">';
    
    foreach ($blocks['results'] as $block) {
        $html .= renderBlock($block);
    }
    
    $html .= '</div>';
    
    return $html;
}


/**
 * Notion 데이터베이스 목록을 가져옴
 * 
 * @return array 데이터베이스 목록
 */
function getNotionDatabases() {
    global $notionApiKey;
    
    // Notion API 엔드포인트 - 검색 API를 사용하여 데이터베이스 목록 가져오기
    $url = "https://api.notion.com/v1/search";
    
    // 데이터베이스만 검색하도록 필터 설정
    $data = [
        'filter' => [
            'value' => 'database',
            'property' => 'object'
        ],
        'sort' => [
            'direction' => 'descending',
            'timestamp' => 'last_edited_time'
        ]
    ];
    
    // JSON 형식으로 인코딩
    $payload = json_encode($data);
    
    // cURL 세션 초기화 및 옵션 설정
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $notionApiKey",
        "Notion-Version: 2022-06-28",
        "Content-Type: application/json"
    ]);
    
    // API 호출 및 응답 받기
    $response = curl_exec($ch);
    
    if(curl_errno($ch)) {
        return ['error' => curl_error($ch)];
    }
    
    curl_close($ch);
    
    // 응답 결과를 JSON에서 PHP 배열로 변환
    return json_decode($response, true);
}

/**
 * 특정 데이터베이스의 페이지 목록 가져오기
 * 
 * @param string $databaseId 데이터베이스 ID
 * @return array 페이지 목록
 */
function getDatabasePages($databaseId) {
    global $notionApiKey;
    
    // Notion API 엔드포인트
    $url = "https://api.notion.com/v1/databases/{$databaseId}/query";

    // 필터 조건 설정
    $data = [
        'filter' => [
            "property" => "is_status",
            "status" => [
                "equals" => "완료"
            ]
        ],
        'sorts' => [
            [
                'property' => 'date',
                'direction' => 'descending'
            ]
        ],
         'page_size' => 100
    ];
    
    // 카테고리 필터가 있는 경우에만 적용
    if (isset($_GET['category'])) {
        $category = $_GET['category'];
        if (isset($data['filter'])) {
            // 이미 필터가 있으면 AND 조건으로 추가
            $data['filter'] = [
                'and' => [
                    $data['filter'],
                    [
                        "property" => "category",
                        "select" => [
                            "equals" => $category
                        ]
                    ]
                ]
            ];
        } else {
            $data['filter'] = [
                "property" => "category",
                "select" => [
                    "equals" => $category
                ]
            ];
        }
    }
    
    // JSON 형식으로 인코딩
    $payload = json_encode($data);
    
    // cURL 세션 초기화 및 옵션 설정
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $notionApiKey",
        "Notion-Version: 2022-06-28",
        "Content-Type: application/json"
    ]);
    
    // API 호출 및 응답 받기
    $response = curl_exec($ch);
    
    if(curl_errno($ch)) {
        return ['error' => curl_error($ch)];
    }
    
    curl_close($ch);
    
    // 응답 결과를 JSON에서 PHP 배열로 변환
    return json_decode($response, true);
}

/**
 * 페이지 정보 추출
 * 
 * @param array $pages 페이지 목록
 * @return array 추출된 페이지 정보
 */
function extractPageInfo($pages) {
    $resultArr = [];
    
    if (isset($pages['results']) && !empty($pages['results'])) {
        foreach ($pages['results'] as $idx => $page) {
            $pageInfo = [
                'id' => $page['id'],
                'title' => '제목 없음',
                'icon' => null,
                'cover' => null,
                'date' => null,
                'last_edited' => date('Y-m-d', strtotime($page['last_edited_time'])),
                'category' => null,
                'status' => null
            ];
            
            // 페이지 아이콘
            if (isset($page['icon']['emoji'])) {
                $pageInfo['icon'] = $page['icon']['emoji'];
            } else if (isset($page['icon']['external']['url'])) {
                $pageInfo['icon'] = $page['icon']['external']['url'];
            }
            
            // 페이지 커버 이미지
            if (isset($page['cover']['external']['url'])) {
                $pageInfo['cover'] = $page['cover']['external']['url'];
            } else if (isset($page['cover']['file']['url'])) {
                $pageInfo['cover'] = $page['cover']['file']['url'];
            }
            
            // 페이지 속성 처리
            if (isset($page['properties'])) {
                foreach ($page['properties'] as $key => $value) {
                    
                    // 날짜 속성
                    if ($key == "date" && isset($value['date']) && isset($value['date']['start'])) {
                        $pageInfo['date'] = $value['date']['start'];
                    } 
                    // 제목 속성
                    else if ($key == "title" && isset($value['title']) && !empty($value['title'])) {
                        $pageInfo['title'] = $value['title'][0]['plain_text'];
                        
                        if (!empty($value['title'][0]['url'])) {
                            $pageInfo['url'] = $value['title'][0]['url'];
                        }
                    } 
                    // 상태 속성
                    else if ($key == "is_status" && isset($value['status']) && isset($value['status']['name'])) {
                        $pageInfo['status'] = $value['status']['name'];
                    }
                    // 카테고리 속성
                    else if ($key == "category" && isset($value['multi_select']) ) {
                        if(!empty($value['multi_select'])){
                            foreach($value['multi_select'] as $multiItem){
                                $pageInfo['category'][] = $multiItem['name'];
                            }
                        }else{
                            $pageInfo['category'][] = "미설정";
                        }
                    }
                }
            }
            
            $resultArr[] = $pageInfo;
        }
    }
    
    return $resultArr;
}

// 페이지 헤더 출력
function outputHeader($title = 'Notion 페이지') {
    $header = <<<HTML
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <link rel="stylesheet" href="./assets/style.css">
    <!-- highlight.js 스타일 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/github.min.css">
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/atom-one-dark.min.css">

    <!-- highlight.js 스크립트 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <!-- 폰트어썸 아이콘 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 코드 블록에 구문 강조 적용
            document.querySelectorAll('pre code').forEach((block) => {
                hljs.highlightBlock(block);
            });
            
            // 토글 메뉴 동작
            const menuToggle = document.getElementById('menu-toggle');
            const sidebarMenu = document.getElementById('sidebar-menu');
            
            if (menuToggle && sidebarMenu) {
                menuToggle.addEventListener('click', function() {
                    sidebarMenu.classList.toggle('active');
                    document.body.classList.toggle('sidebar-open');
                });
            }
            
            // 모바일에서 메뉴 항목 클릭 시 사이드바 닫기
            const menuItems = document.querySelectorAll('#sidebar-menu a');
            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    if (window.innerWidth < 768) {
                        sidebarMenu.classList.remove('active');
                        document.body.classList.remove('sidebar-open');
                    }
                });
            });
        });
    </script>
</head>
<body>
    <header class="site-header">
        <a href="notion" class="logo">
            <i class="fas fa-book"></i> notion
        </a>
        <button id="menu-toggle" class="menu-toggle">
            <i class="fas fa-bars"></i>
        </button>
    </header>
    
    <aside id="sidebar-menu" class="sidebar">
HTML;
    
    return $header;
}

// 사이드바 메뉴 출력
function outputSidebar($databases) {
    $sidebar = '<h3>NOTION WIKI</h3>';
    $sidebar .= '<ul>';
    
    // 모든 데이터베이스 표시
    $sidebar .= '<li><a href="notion"><i class="fas fa-home"></i> 홈</a></li>';
    
    // 각 데이터베이스 표시
    if (isset($databases['results']) && !empty($databases['results'])) {
        foreach ($databases['results'] as $db) {
            $title = isset($db['title'][0]['plain_text']) ? $db['title'][0]['plain_text'] : '이름 없는 데이터베이스';
            $icon = isset($db['icon']['emoji']) ? $db['icon']['emoji'] . ' ' : '<i class="fas fa-database"></i> ';
            $sidebar .= '<li><a href="notion?db=' .str_replace('-', '', $db['id']) . '">' . $icon . $title . '</a></li>';
        }
    }
    
    $sidebar .= '</ul>';
    
    
    return $sidebar . '</aside>';
}

// 페이지 푸터 출력
function outputFooter() {
    $footer = <<<HTML
        <footer class="site-footer">
            <p>&copy; 2025 위키 노션. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>
HTML;
    
    return $footer;
}

// 메인 콘텐츠 시작
function startMainContent() {
    return '<main class="main-content"><div class="container">';
}

// 페이지 렌더링
if (isset($_GET['page_id'])) {
    // 단일 페이지 표시
    $pageId = $_GET['page_id'];
    
    // 페이지 제목 가져오기
    $titleUrl = "https://api.notion.com/v1/blocks/{$pageId}";
    $ch = curl_init($titleUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$notionApiKey}",
        "Notion-Version: 2022-06-28",
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $pageInfo = json_decode($response, true);
    $pageTitle = 'Notion 페이지';
    
    if (isset($pageInfo['child_page']) && isset($pageInfo['child_page']['title'])) {
        $pageTitle = $pageInfo['child_page']['title'];
    }
    
    // 모든 데이터베이스 가져오기
    $databases = getNotionDatabases();
    
    // 페이지 HTML 출력
    echo outputHeader($pageTitle);
    echo outputSidebar($databases);
    echo startMainContent();
    echo '<div class="wiki-container">';
    echo '<a href="javascript:history.back(-1)" class="back-button"><i class="fas fa-arrow-left"></i> 뒤로가기</a>';
    echo '<h1 class="wiki-title">' . htmlspecialchars($pageTitle) . '</h1>';
    echo getNotionPageAsHtml($pageId);
    echo '</div>';
    // echo outputFooter();
} else {

    // 모든 데이터베이스 가져오기
    $databases = getNotionDatabases();

    // 페이지 HTML 출력
    echo outputHeader('위키 노션');
    echo outputSidebar($databases);
    echo startMainContent();
    
    // 특정 데이터베이스의 페이지 목록 표시
    if(!empty($_GET['db'])){
        $dbId = $_GET['db']; 
        
        // 데이터베이스 페이지 가져오기
        $pages = getDatabasePages($dbId);
        // 페이지 정보 추출
        $pagesList = extractPageInfo($pages);
       
        // echo '<h1 class="section-title">' . htmlspecialchars($dbTitle) . '</h1>';
        
        getList($pagesList);
    }else{
       
        echo '<h1 class="section-title"> 목록</h1>';
        
        if (isset($databases['results']) && !empty($databases['results'])) {
            foreach ($databases['results'] as $db) {
                $title = isset($db['title'][0]['plain_text']) ? $db['title'][0]['plain_text'] : '이름 없는 데이터베이스';
                $icon = isset($db['icon']['emoji']) ? $db['icon']['emoji'] : '<i class="fas fa-database"></i>';
                
                // 데이터베이스 페이지 수 가져오기
                $dbPages = getDatabasePages(str_replace('-', '', $db['id']));
                $pageCount = isset($dbPages['results']) ? count($dbPages['results']) : 0;
                
                echo '<div class="database-card">';
                echo '<div class="database-card-header">';
                echo '<div class="database-card-icon">' . $icon . '</div>';
                echo '<a href="notion?db=' . str_replace('-', '', $db['id']) . '" class="database-card-title">' . htmlspecialchars($title) . '</a>';
                echo '</div>';
                echo '<div class="database-card-body">';
                echo '<div class="database-card-meta">';
                echo '<span><i class="far fa-clock"></i> 최종 수정: ' . date('Y-m-d', strtotime($db['last_edited_time'])) . '</span>';
                echo '<span class="database-card-count"><i class="fas fa-file-alt"></i> ' . $pageCount . ' 페이지</span>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<p>사용 가능한 데이터베이스가 없습니다.</p>';
        }
    }
    
    
    // echo outputFooter();
}

function getList($pagesList){

    // 카테고리별 페이지 표시
    if (!empty($pagesList)) {
        $groupedPages = [];
        foreach ($pagesList as $page) {
            if(isset($page['category']) ){
                foreach($page['category'] as $category){
                    if(!isset($groupedPages[$category])){
                        $groupedPages[$category] = [];
                    }
                }
                $groupedPages[$category][] = $page;
            }
        }
        
        // 카테고리별로 페이지 카드 표시 (최대 4개씩)
        foreach ($groupedPages as $category => $pages) {
            echo '<div class="category-section">';
            echo '<h2 class="section-title">';
            echo htmlspecialchars($category);
            // echo ' <a href="notion?db='.$_GET['db'].'&category=' . urlencode($category) . '" style="font-size:0.7em; color:var(--link-color);">전체보기</a>';
            echo '</h2>';
            echo '<div class="pages-grid">';
            
            // 최대 4개의 페이지만 표시
            $displayPages = array_slice($pages, 0, 4);
            
            foreach ($displayPages as $page) {
                $coverStyle = $page['cover'] ? ' style="background-image: url(\'' . $page['cover'] . '\')"' : '';
                $iconHtml = '';
                
                if ($page['icon']) {
                    if (is_string($page['icon']) && strlen($page['icon']) < 10) {
                        // Emoji icon
                        $iconHtml = '<span class="page-card-icon">' . $page['icon'] . '</span>';
                    } else {
                        // URL icon
                        $iconHtml = '<img src="' . $page['icon'] . '" alt="icon" class="page-card-icon" style="width: 16px; height: 16px; vertical-align: middle;">';
                    }
                }
               
                echo '<div class="page-card">';
                if ($page['cover']) {
                    echo '<div class="page-card-cover"' . $coverStyle . '></div>';
                }
                echo '<div class="page-card-content">';
                echo '<a href="notion?page_id=' . $page['id'] . '" class="page-card-title">' . $iconHtml . htmlspecialchars($page['title']) . '</a>';
                
                echo '<div class="page-card-tags">';
                if (isset($page['category'])) {
                    foreach($page['category'] as $category){
                        echo '<span class="category-tag">' . htmlspecialchars($category) . '</span>';
                    }
                }
                if ($page['status']) {
                    echo '<span class="status-tag">' . htmlspecialchars($page['status']) . '</span>';
                }
                echo '</div>';
                
                echo '<div class="page-card-meta">';
                if ($page['date']) {
                    echo '<span><i class="far fa-calendar-alt"></i> ' . $page['date'] . '</span>';
                }
                echo '<span><i class="far fa-edit"></i> ' . $page['last_edited'] . '</span>';
                echo '</div>';
                
                echo '</div>';
                echo '</div>';
            }
            
            echo '</div>';
            echo '</div>';
        }
    }
}

/**
 * Notion 블록을 HTML로 변환
 * 
 * @param array $block Notion API에서 반환한 블록 정보
 * @return string HTML 형식으로 변환된 블록
 */
function renderBlock($block) {
    $type = $block['type'];
    $html = '';
    
    switch ($type) {
        case 'paragraph':
            $html .= '<p class="wiki-paragraph">';
            if (isset($block['paragraph']['rich_text']) && !empty($block['paragraph']['rich_text'])) {
                foreach ($block['paragraph']['rich_text'] as $text) {
                    $html .= renderRichText($text);
                }
            }
            $html .= '</p>';
            break;
            
        case 'heading_1':
            $html .= '<h1 class="wiki-heading">';
            if (isset($block['heading_1']['rich_text']) && !empty($block['heading_1']['rich_text'])) {
                foreach ($block['heading_1']['rich_text'] as $text) {
                    $html .= renderRichText($text);
                }
            }
            $html .= '</h1>';
            break;
            
        case 'heading_2':
            $html .= '<h2 class="wiki-heading">';
            if (isset($block['heading_2']['rich_text']) && !empty($block['heading_2']['rich_text'])) {
                foreach ($block['heading_2']['rich_text'] as $text) {
                    $html .= renderRichText($text);
                }
            }
            $html .= '</h2>';
            break;
            
        case 'heading_3':
            $html .= '<h3 class="wiki-heading">';
            if (isset($block['heading_3']['rich_text']) && !empty($block['heading_3']['rich_text'])) {
                foreach ($block['heading_3']['rich_text'] as $text) {
                    $html .= renderRichText($text);
                }
            }
            $html .= '</h3>';
            break;
            
        case 'bulleted_list_item':
            $html .= '<li class="wiki-list-item">';
            if (isset($block['bulleted_list_item']['rich_text']) && !empty($block['bulleted_list_item']['rich_text'])) {
                foreach ($block['bulleted_list_item']['rich_text'] as $text) {
                    $html .= renderRichText($text);
                }
            }
            $html .= '</li>';
            break;
            
        case 'numbered_list_item':
            $html .= '<li class="wiki-list-item">';
            if (isset($block['numbered_list_item']['rich_text']) && !empty($block['numbered_list_item']['rich_text'])) {
                foreach ($block['numbered_list_item']['rich_text'] as $text) {
                    $html .= renderRichText($text);
                }
            }
            $html .= '</li>';
            break;
            
        case 'to_do':
            $checked = isset($block['to_do']['checked']) && $block['to_do']['checked'] ? ' checked' : '';
            $html .= '<div class="wiki-todo"><input type="checkbox"' . $checked . ' disabled>';
            if (isset($block['to_do']['rich_text']) && !empty($block['to_do']['rich_text'])) {
                foreach ($block['to_do']['rich_text'] as $text) {
                    $html .= renderRichText($text);
                }
            }
            $html .= '</div>';
            break;
            
        case 'toggle':
            $html .= '<details class="wiki-toggle"><summary>';
            if (isset($block['toggle']['rich_text']) && !empty($block['toggle']['rich_text'])) {
                foreach ($block['toggle']['rich_text'] as $text) {
                    $html .= renderRichText($text);
                }
            }
            $html .= '</summary>';
            
            // 토글 내부 블록 처리 (비동기 호출 필요)
            if (isset($block['has_children']) && $block['has_children']) {
                $html .= '<div class="toggle-content">토글 내용을 표시하려면 추가 API 호출이 필요합니다.</div>';
            }
            
            $html .= '</details>';
            break;
            
        case 'code':
            $language = isset($block['code']['language']) ? $block['code']['language'] : '';
            $html .= '<div class="wiki-code"><pre><code class=" language-' . $language . '">';
            if (isset($block['code']['rich_text']) && !empty($block['code']['rich_text'])) {
                foreach ($block['code']['rich_text'] as $text) {
                    $html .= htmlspecialchars($text['plain_text']);
                }
            }
            $html .= '</code></pre></div>';
            break;
            
        case 'image':
            $html .= '<div class="wiki-image">';
            if (isset($block['image']['file']['url'])) {
                $html .= '<figure>';
                $html .= '<img src="' . $block['image']['file']['url'] . '" alt="Notion 이미지">';
                $html .= '</figure>';
            } else if (isset($block['image']['external']['url'])) {
                $html .= '<figure>';
                $html .= '<img src="' . $block['image']['external']['url'] . '" alt="Notion 이미지">';
                $html .= '</figure>';
            }
            $html .= '</div>';
            break;
            
        case 'divider':
            $html .= '<hr class="wiki-divider">';
            break;
            
        case 'quote':
            $html .= '<blockquote class="wiki-quote">';
            if (isset($block['quote']['rich_text']) && !empty($block['quote']['rich_text'])) {
                foreach ($block['quote']['rich_text'] as $text) {
                    $html .= renderRichText($text);
                }
            }
            $html .= '</blockquote>';
            break;
            
        case 'callout':
            $emoji = isset($block['callout']['icon']['emoji']) ? $block['callout']['icon']['emoji'] : '💡';
            $html .= '<div class="wiki-callout">';
            $html .= '<div class="callout-icon">' . $emoji . '</div>';
            $html .= '<div class="callout-content">';
            if (isset($block['callout']['rich_text']) && !empty($block['callout']['rich_text'])) {
                foreach ($block['callout']['rich_text'] as $text) {
                    $html .= renderRichText($text);
                }
            }
            $html .= '</div>';
            $html .= '</div>';
            break;
            
        default:
            $html .= '<div class="wiki-unsupported">지원되지 않는 블록 유형: ' . $type . '</div>';
    }
    
    return $html;
}



/**
 * Rich Text 블록을 HTML로 변환
 * 
 * @param array $text Rich Text 객체
 * @return string HTML 형식으로 변환된 텍스트
 */
function renderRichText($text) {
    $content = $text['plain_text'];
    $html = htmlspecialchars($content);
    
    // 서식 적용
    if (isset($text['annotations'])) {
        if ($text['annotations']['bold']) {
            $html = '<strong>' . $html . '</strong>';
        }
        if ($text['annotations']['italic']) {
            $html = '<em>' . $html . '</em>';
        }
        if ($text['annotations']['strikethrough']) {
            $html = '<s>' . $html . '</s>';
        }
        if ($text['annotations']['underline']) {
            $html = '<u>' . $html . '</u>';
        }
        if ($text['annotations']['code']) {
            $html = '<code class="wiki-inline-code">' . $html . '</code>';
        }
    }
    
    // 링크 처리
    if (isset($text['href']) && $text['href']) {
        $html = '<a href="' . $text['href'] . '" target="_blank" class="wiki-link">' . $html . '</a>';
    }
    
    return $html;
}

?>

