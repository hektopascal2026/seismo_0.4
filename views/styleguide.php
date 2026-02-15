<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Style Guide - Seismo</title>
    <link rel="stylesheet" href="<?= getBasePath() ?>/assets/css/style.css">
    <style>
        .styleguide-section {
            margin-bottom: 60px;
            padding-bottom: 40px;
            border-bottom: 2px solid #000000;
        }
        
        .styleguide-section:last-child {
            border-bottom: none;
        }
        
        .styleguide-section h2 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 30px;
        }
        
        .styleguide-section h3 {
            font-size: 18px;
            font-weight: 700;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        
        .styleguide-section p {
            font-size: 12px;
            color: #000000;
        }

        .color-swatch {
            display: inline-block;
            width: 120px;
            height: 120px;
            border: 2px solid #000000;
            margin-right: 20px;
            margin-bottom: 20px;
            vertical-align: top;
            position: relative;
        }
        
        .color-swatch-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: rgba(255, 255, 255, 0.95);
            padding: 8px;
            font-size: 12px;
            font-weight: 600;
            border-top: 2px solid #000000;
        }
        
        .logo-showcase {
            display: flex;
            gap: 40px;
            align-items: center;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        
        .logo-variant {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        
        .logo-variant-label {
            font-size: 12px;
            font-weight: 600;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .component-demo {
            border: 1px solid #000000;
            padding: 20px;
            margin: 20px 0;
            background-color: #ffffff;
        }
        
        .code-block {
            background-color: #f5f5f5;
            border: 1px solid #000000;
            padding: 15px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
        }
        
        .typography-sample {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid #000000;
            background-color: #fafafa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="top-bar">
            <div class="top-bar-left">
                <span class="top-bar-title">
                    <a href="?action=index">
                        <svg class="logo-icon logo-icon-large" viewBox="0 0 24 16" xmlns="http://www.w3.org/2000/svg">
                            <rect width="24" height="16" fill="#FFFFC5"/>
                            <path d="M0,8 L4,12 L6,4 L10,10 L14,2 L18,8 L20,6 L24,8" stroke="#000000" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    Style Guide
                </span>
                <span class="top-bar-subtitle">Design system documentation for Seismo</span>
            </div>
            <div class="top-bar-actions">
                <button type="button" class="top-bar-btn" id="menuToggle" title="Menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
            </div>
        </div>

        <nav class="nav-drawer" id="navDrawer">
            <a href="?action=index" class="nav-link">Feed</a>
            <a href="?action=magnitu" class="nav-link">Magnitu</a>
            <a href="?action=feeds" class="nav-link">RSS</a>
            <a href="?action=lex" class="nav-link">Lex</a>
            <a href="?action=mail" class="nav-link">Mail</a>
            <a href="?action=substack" class="nav-link">Substack</a>
            <a href="?action=settings" class="nav-link">Settings</a>
            <a href="?action=about" class="nav-link">About</a>
            <a href="?action=beta" class="nav-link">Beta</a>
        </nav>

        <!-- Logo -->
        <section class="styleguide-section">
            <h2>Logo</h2>
            <p>Black waveform on light yellow (#FFFFC5) background. Use <code>.logo-icon</code> for inline (1em height) and <code>.logo-icon-large</code> for header size (24px).</p>
            
            <div class="logo-showcase">
                <div class="logo-variant">
                    <div class="logo-variant-label">Inline (1em)</div>
                    <svg class="logo-icon" viewBox="0 0 24 16" xmlns="http://www.w3.org/2000/svg" style="height: 16px;">
                        <rect width="24" height="16" fill="#FFFFC5"/>
                        <path d="M0,8 L4,12 L6,4 L10,10 L14,2 L18,8 L20,6 L24,8" stroke="#000000" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="logo-variant">
                    <div class="logo-variant-label">Large (24px)</div>
                    <svg class="logo-icon logo-icon-large" viewBox="0 0 24 16" xmlns="http://www.w3.org/2000/svg">
                        <rect width="24" height="16" fill="#FFFFC5"/>
                        <path d="M0,8 L4,12 L6,4 L10,10 L14,2 L18,8 L20,6 L24,8" stroke="#000000" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
        </section>

        <!-- Colors -->
        <section class="styleguide-section">
            <h2>Colors</h2>
            <p>Minimal high-contrast palette. All text is black (#000000). White text only on dark button backgrounds for visibility.</p>
            
            <h3>Core</h3>
            <div>
                <div class="color-swatch" style="background-color: #000000;">
                    <div class="color-swatch-info">#000000<br>Black</div>
                </div>
                <div class="color-swatch" style="background-color: #FFFFFF;">
                    <div class="color-swatch-info">#FFFFFF<br>White</div>
                </div>
                <div class="color-swatch" style="background-color: #FFFFC5;">
                    <div class="color-swatch-info">#FFFFC5<br>Yellow</div>
                </div>
                <div class="color-swatch" style="background-color: #F5F5F5;">
                    <div class="color-swatch-info">#F5F5F5<br>Light Gray</div>
                </div>
            </div>
            
            <h3>Semantic (button borders)</h3>
            <div>
                <div class="color-swatch" style="background-color: #FF2C2C;">
                    <div class="color-swatch-info">#FF2C2C<br>Danger</div>
                </div>
                <div class="color-swatch" style="background-color: #ff9900;">
                    <div class="color-swatch-info">#ff9900<br>Warning</div>
                </div>
                <div class="color-swatch" style="background-color: #00aa00;">
                    <div class="color-swatch-info">#00aa00<br>Success</div>
                </div>
            </div>

            <h3>Tag Colors</h3>
            <div>
                <div class="color-swatch" style="background-color: #add8e6;">
                    <div class="color-swatch-info">#add8e6<br>RSS Tags</div>
                </div>
                <div class="color-swatch" style="background-color: #FFDBBB;">
                    <div class="color-swatch-info">#FFDBBB<br>Email Tags</div>
                </div>
                <div class="color-swatch" style="background-color: #C5B4D1;">
                    <div class="color-swatch-info">#C5B4D1<br>Substack Tags</div>
                </div>
                <div class="color-swatch" style="background-color: #f5f562;">
                    <div class="color-swatch-info">#f5f562<br>Lex Tags</div>
                </div>
            </div>
        </section>

        <!-- Typography -->
        <section class="styleguide-section">
            <h2>Typography</h2>
            <p>System font stack. Three sizes only. All text is black (#000000).</p>
            
            <div class="code-block">-apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif</div>
            
            <div class="typography-sample">
                <p style="margin: 0; font-size: 18px; font-weight: 700;">Big &mdash; 18px, 700 &mdash; page titles, headings (h2/h3), navigation links</p>
            </div>
            <div class="typography-sample">
                <p style="margin: 0; font-size: 14px; font-weight: 600;">Medium &mdash; 14px, 600 &mdash; buttons, card titles, inputs, form labels</p>
            </div>
            <div class="typography-sample">
                <p style="margin: 0; font-size: 12px; font-weight: 400;">Small &mdash; 12px, 400 &mdash; tags, card text, dates, status lines, subtitles</p>
            </div>
        </section>

        <!-- Navigation -->
        <section class="styleguide-section">
            <h2>Navigation</h2>
            <p>Compact top bar with logo, page title, subtitle, and icon buttons (refresh, search, menu). The navigation drawer opens below the top bar as a horizontal row (desktop) or vertical list (mobile). Active page is highlighted with black background.</p>

            <div class="component-demo">
                <nav class="nav-drawer" style="display: flex;">
                    <a href="#" class="nav-link active">Active</a>
                    <a href="#" class="nav-link">Inactive</a>
                    <a href="#" class="nav-link">Another</a>
                </nav>
            </div>
        </section>

        <!-- Buttons -->
        <section class="styleguide-section">
            <h2>Buttons</h2>
            <p>2px border, all white background with black text. Hover adds <code>box-shadow: 2px 2px 0px</code> (same as cards). Padding: 8px 16px, font-size: 14px (medium), font-weight: 600.</p>

            <div class="component-demo">
                <div style="display: flex; gap: 12px; flex-wrap: wrap; margin: 20px 0;">
                    <a href="#" class="btn btn-primary">Primary</a>
                    <a href="#" class="btn btn-secondary">Secondary</a>
                    <a href="#" class="btn btn-danger">Danger</a>
                    <a href="#" class="btn btn-warning">Warning</a>
                    <a href="#" class="btn btn-success">Success</a>
                </div>
                <p>All buttons are white with black text. <strong>Danger:</strong> #FF2C2C border + shadow. <strong>Warning:</strong> #ff9900 border + shadow. <strong>Success:</strong> #00aa00 border + shadow.</p>
            </div>
        </section>

        <!-- Cards -->
        <section class="styleguide-section">
            <h2>Cards</h2>
            <p>2px black border, 14px 16px padding. Hover adds <code>box-shadow: 2px 2px 0px #000000</code>.</p>
            <p>Layout: <strong>top-left</strong> = user-assigned tag (<code>.entry-tag</code>, 12px small, 2px black border), <strong>top-right</strong> = context (document type for Lex). <strong>Bottom-left</strong> = expand/collapse, <strong>bottom-right</strong> = date. Title = 14px medium. Body text = 12px small.</p>
            
            <h3>RSS Card</h3>
            <div class="component-demo">
                <div class="entry-card">
                    <div class="entry-header">
                        <span class="entry-tag" style="background-color: #add8e6;">user-tag</span>
                    </div>
                    <h3 class="entry-title">
                        <a href="#">Entry Title Example</a>
                    </h3>
                    <div class="entry-content entry-preview">
                        Preview text truncated to 200 characters. Cards display feed items, emails, and Substack posts with consistent styling across all pages...
                        <a href="#" class="entry-link" style="margin-left: 4px;">Read more &rarr;</a>
                    </div>
                    <div class="entry-full-content" style="display:none">Full expanded content shown when the user clicks expand.</div>
                    <div class="entry-actions">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <button class="btn btn-secondary entry-expand-btn">&#9660; expand</button>
                        </div>
                        <span class="entry-date">24.01.2026 12:00</span>
                    </div>
                </div>
            </div>

            <h3>Email Card</h3>
            <div class="component-demo">
                <div class="entry-card">
                    <div class="entry-header">
                        <span class="entry-tag" style="background-color: #FFDBBB;">sender-tag</span>
                    </div>
                    <h3 class="entry-title">Email Subject Line</h3>
                    <div class="entry-content entry-preview">Email body preview truncated to 200 chars...</div>
                    <div class="entry-actions">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <button class="btn btn-secondary entry-expand-btn">&#9660; expand</button>
                        </div>
                        <span class="entry-date">24.01.2026 12:00</span>
                    </div>
                </div>
            </div>

            <h3>Substack Card</h3>
            <div class="component-demo">
                <div class="entry-card">
                    <div class="entry-header">
                        <span class="entry-tag" style="background-color: #C5B4D1;">user-tag</span>
                    </div>
                    <h3 class="entry-title"><a href="#">Substack Post Title</a></h3>
                    <div class="entry-content entry-preview">
                        Post content preview...
                        <a href="#" class="entry-link" style="margin-left: 4px;">Read more &rarr;</a>
                    </div>
                    <div class="entry-actions">
                        <span></span>
                        <span class="entry-date">24.01.2026 12:00</span>
                    </div>
                </div>
            </div>

            <h3>Lex Card</h3>
            <div class="component-demo">
                <div class="entry-card">
                    <div class="entry-header">
                        <span class="entry-tag" style="background-color: #f5f562;">🇨🇭 CH</span>
                        <span class="entry-tag" style="background-color: #f5f5f5;">Bundesgesetz</span>
                    </div>
                    <h3 class="entry-title"><a href="#">Bundesgesetz über die Beispielregelung</a></h3>
                    <div class="entry-actions">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-family: monospace;">eli/fga/2025/1234</span>
                            <a href="#" class="entry-link">Fedlex &rarr;</a>
                        </div>
                        <span class="entry-date">17.12.2025</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Expand / Collapse -->
        <section class="styleguide-section">
            <h2>Expand / Collapse</h2>
            <p>Entries with content longer than 200 characters get a toggle button. Per-entry: "&#9660; expand" / "&#9650; collapse". Global: "&#9660; expand all" / "&#9650; collapse all" in the section title row.</p>
            
            <h3>Section Title with Global Toggle</h3>
            <div class="component-demo">
                <div class="section-title-row">
                    <h2 class="section-title" style="margin-bottom: 0;">Refreshed: 24.01.2026 12:00</h2>
                    <button class="btn btn-secondary entry-expand-all-btn">&#9660; expand all</button>
                </div>
            </div>
            
            <h3>Per-Entry Buttons</h3>
            <div class="component-demo">
                <div style="display: flex; gap: 12px;">
                    <button class="btn btn-secondary">&#9660; expand</button>
                    <button class="btn btn-secondary">&#9650; collapse</button>
                </div>
            </div>
        </section>

        <!-- Tag Filters -->
        <section class="styleguide-section">
            <h2>Tag Filters</h2>
            <p>Two filter patterns: checkbox pills (main page, multi-select) and category buttons (RSS, Mail, Substack pages, single-select).</p>
            
            <h3>Checkbox Pills (Main Page)</h3>
            <p>All tag types in one row. Color distinguishes source: RSS #add8e6, Email #FFDBBB, Substack #C5B4D1, Lex #f5f562. Size: 12px small, 4px 10px padding, 2px black border.</p>
            <div class="component-demo">
                <div class="tag-filter-list">
                    <label class="tag-filter-pill" style="background-color: #add8e6;">
                        <input type="checkbox" checked>
                        <span>RSS Tag</span>
                    </label>
                    <label class="tag-filter-pill">
                        <input type="checkbox">
                        <span>RSS Inactive</span>
                    </label>
                    <label class="tag-filter-pill" style="background-color: #FFDBBB;">
                        <input type="checkbox" checked>
                        <span>Email Tag</span>
                    </label>
                    <label class="tag-filter-pill" style="background-color: #C5B4D1;">
                        <input type="checkbox" checked>
                        <span>Substack Tag</span>
                    </label>
                    <label class="tag-filter-pill" style="background-color: #f5f562;">
                        <input type="checkbox" checked>
                        <span>Lex Tag</span>
                    </label>
                    <label class="tag-filter-pill">
                        <input type="checkbox">
                        <span>Inactive</span>
                    </label>
                </div>
            </div>
            
            <h3>Category Buttons (RSS, Lex, Mail, Substack Pages)</h3>
            <p>Single-select filter. Size: 12px small, 6px 12px padding, 2px black border.</p>
            <div class="component-demo">
                <div class="category-filter">
                    <a href="#" class="category-btn" style="background-color: #add8e6;">All</a>
                    <a href="#" class="category-btn">Category 1</a>
                    <a href="#" class="category-btn">Category 2</a>
                </div>
            </div>
        </section>

        <!-- Tag Inputs -->
        <section class="styleguide-section">
            <h2>Tag Inputs (Settings)</h2>
            <p>Editable tag inputs on the settings page. Press Enter to save, Escape to cancel. Visual feedback: gray border while saving, green border/background on success.</p>
            
            <div class="component-demo">
                <h3>Per-Feed Tag</h3>
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px;">
                    <label style="font-weight: 600;">Tag:</label>
                    <div class="tag-input-wrapper">
                        <input type="text" class="feed-tag-input" value="example-tag" style="width: 150px;" readonly>
                    </div>
                </div>

                <h3>"All Tags" Rename</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <div class="feed-tag-input-wrapper" style="display: inline-flex;">
                        <input type="text" class="feed-tag-input" value="tag-name" style="width: auto; min-width: 100px; padding: 6px 12px;" readonly>
                    </div>
                </div>
                
                <h3>Save States</h3>
                <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-top: 10px;">
                    <div>
                        <div style="font-size: 12px; font-weight: 600; margin-bottom: 4px;">DEFAULT</div>
                        <input type="text" class="feed-tag-input" value="normal" style="width: 120px;" readonly>
                    </div>
                    <div>
                        <div style="font-size: 12px; font-weight: 600; margin-bottom: 4px;">SAVING</div>
                        <input type="text" class="feed-tag-input feed-tag-saving" value="saving..." style="width: 120px;" readonly>
                    </div>
                    <div>
                        <div style="font-size: 12px; font-weight: 600; margin-bottom: 4px;">SAVED</div>
                        <input type="text" class="feed-tag-input feed-tag-saved" value="saved" style="width: 120px;" readonly>
                    </div>
                </div>
            </div>
        </section>

        <!-- Messages -->
        <section class="styleguide-section">
            <h2>Messages</h2>
            <p>Feedback messages: 2px border, 12px small text.</p>
            
            <div class="component-demo">
                <div class="message message-success">Success: Operation completed.</div>
                <div class="message message-error">Error: Something went wrong.</div>
                <div class="message message-info">Info: Informational message.</div>
            </div>
        </section>

        <!-- Forms -->
        <section class="styleguide-section">
            <h2>Forms</h2>
            <p>Inputs: 2px black border, 14px medium font. Focus: #fafafa background.</p>
            
            <div class="component-demo">
                <input type="text" class="search-input" placeholder="Search input" style="margin-bottom: 15px; display: block; width: 100%; max-width: 400px;">
                <input type="text" class="feed-input" placeholder="Feed/URL input" style="display: block; width: 100%; max-width: 400px;">
            </div>
        </section>

        <!-- Search Highlight -->
        <section class="styleguide-section">
            <h2>Search Highlight</h2>
            <p>Matching search terms highlighted with yellow background (#FFFFC5).</p>
            
            <div class="component-demo">
                <p>Example text with <mark class="search-highlight">highlighted terms</mark> matching the query.</p>
            </div>
        </section>

        <!-- Spacing & Borders -->
        <section class="styleguide-section">
            <h2>Spacing &amp; Borders</h2>
            
            <div class="component-demo">
                <p><strong>Container:</strong> max-width 1200px, padding 20px</p>
                <p><strong>Cards:</strong> 14px 16px padding, 10px gap between cards</p>
                <p><strong>Buttons:</strong> 8px 16px padding</p>
                <p><strong>Nav:</strong> 10px 20px padding per tab, 18px big font</p>
                <p><strong>Section gaps:</strong> 16-24px between sections</p>
            </div>
            
            <div class="component-demo">
                <div style="border: 2px solid #000000; padding: 20px; margin: 10px 0;">2px solid &mdash; buttons, cards, nav tabs, inputs, tags</div>
                <div style="border: 1px solid #000000; padding: 20px; margin: 10px 0;">1px solid &mdash; messages, settings items, dividers</div>
            </div>
        </section>

        <!-- Hover Effects -->
        <section class="styleguide-section">
            <h2>Hover Effects</h2>
            <p>All interactive elements share the same hover: <code>box-shadow: 2px 2px 0px #000000</code>. This applies to cards, buttons, tags, pills, inputs, and nav links.</p>
            
            <div class="component-demo">
                <p>Hover the card and button below:</p>
                <div class="entry-card" style="max-width: 400px; margin: 16px 0;">
                    <div class="entry-header">
                        <span class="entry-tag" style="background-color: #add8e6;">Card hover</span>
                    </div>
                    <h3 class="entry-title"><a href="#">Shadow appears on hover</a></h3>
                </div>
                <div style="display: flex; gap: 12px;">
                    <a href="#" class="btn btn-secondary">Button hover</a>
                </div>
            </div>
        </section>
    </div>

    <script>
    (function() {
        function collapseEntry(card, btn) {
            var preview = card.querySelector('.entry-preview');
            var full = card.querySelector('.entry-full-content');
            if (!preview || !full) return;
            full.style.display = 'none';
            preview.style.display = '';
            if (btn) btn.textContent = '\u25BC expand';
        }

        function expandEntry(card, btn) {
            var preview = card.querySelector('.entry-preview');
            var full = card.querySelector('.entry-full-content');
            if (!preview || !full) return;
            preview.style.display = 'none';
            full.style.display = 'block';
            if (btn) btn.textContent = '\u25B2 collapse';
        }

        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.entry-expand-btn');
            if (!btn) return;
            var card = btn.closest('.entry-card');
            if (!card) return;
            var full = card.querySelector('.entry-full-content');
            if (!full) return;
            if (full.style.display === 'block') {
                collapseEntry(card, btn);
            } else {
                expandEntry(card, btn);
            }
        });

        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.entry-expand-all-btn');
            if (!btn) return;
            var isExpanded = btn.dataset.expanded === 'true';
            document.querySelectorAll('.entry-card').forEach(function(card) {
                var entryBtn = card.querySelector('.entry-expand-btn');
                if (isExpanded) {
                    collapseEntry(card, entryBtn);
                } else {
                    expandEntry(card, entryBtn);
                }
            });
            btn.dataset.expanded = !isExpanded;
            btn.textContent = !isExpanded ? '\u25B2 collapse all' : '\u25BC expand all';
        });
    })();
    </script>
    <script>
    (function() {
        var menuBtn = document.getElementById('menuToggle');
        var navDrawer = document.getElementById('navDrawer');
        menuBtn.addEventListener('click', function() {
            navDrawer.classList.toggle('open');
            menuBtn.classList.toggle('active');
        });
    })();
    </script>
</body>
</html>
