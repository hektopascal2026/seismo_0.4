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
            <a href="?action=calendar" class="nav-link">Calendar</a>
            <a href="?action=lex" class="nav-link">Lex</a>
            <a href="?action=jus" class="nav-link">Jus</a>
            <a href="?action=mail" class="nav-link">Mail</a>
            <a href="?action=substack" class="nav-link">Substack</a>
            <a href="?action=scraper" class="nav-link">Scraper</a>
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
            <p>Minimal high-contrast palette. All text is black (#000000). Coral (#FF6B6B) is the brand accent color. White text only on dark button backgrounds for visibility.</p>
            
            <h3>Core</h3>
            <div>
                <div class="color-swatch" style="background-color: #000000;">
                    <div class="color-swatch-info">#000000<br>Black</div>
                </div>
                <div class="color-swatch" style="background-color: #FFFFFF;">
                    <div class="color-swatch-info">#FFFFFF<br>White</div>
                </div>
                <div class="color-swatch" style="background-color: #FF6B6B;">
                    <div class="color-swatch-info">#FF6B6B<br>Coral Accent</div>
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
            <p>Layout: <strong>top-left</strong> = tags (<code>.entry-tag</code>, 12px small, 2px black border) with 6px gap between them, <strong>top-right</strong> = Magnitu score badge (pushed right via <code>margin-left: auto</code>). <strong>Bottom-left</strong> = expand/collapse, <strong>bottom-right</strong> = date. Title = 14px medium. Body text = 12px small. Lex cards show two tags: source flag + document type.</p>
            
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
                            <button class="btn btn-secondary entry-expand-btn">expand &#9660;</button>
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
                            <button class="btn btn-secondary entry-expand-btn">expand &#9660;</button>
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

            <h3>Lex Card (CH)</h3>
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

            <h3>Lex Card (DE)</h3>
            <div class="component-demo">
                <div class="entry-card">
                    <div class="entry-header">
                        <span class="entry-tag" style="background-color: #f5f562;">🇩🇪 DE</span>
                        <span class="entry-tag" style="background-color: #f5f5f5;">Verordnung</span>
                    </div>
                    <h3 class="entry-title"><a href="#">Verordnung über die Beteiligung der maßgeblichen Organisationen</a></h3>
                    <div class="entry-actions">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-family: monospace;">bgbl-1/2026/41</span>
                            <a href="#" class="entry-link">recht.bund.de &rarr;</a>
                        </div>
                        <span class="entry-date">13.02.2026</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Expand / Collapse -->
        <section class="styleguide-section">
            <h2>Expand / Collapse</h2>
            <p>Entries with content longer than 200 characters get a toggle button. Per-entry: "expand &#9660;" / "collapse &#9650;". Global: "expand all &#9660;" / "collapse all &#9650;" in the section title row. Both use compact sizing (12px, 4px 10px padding) matching the sort button.</p>
            
            <h3>Section Title with Global Toggle</h3>
            <div class="component-demo">
                <div class="section-title-row">
                    <h2 class="section-title" style="margin-bottom: 0;">Refreshed: 24.01.2026 12:00</h2>
                    <button class="btn btn-secondary entry-expand-all-btn">expand all &#9660;</button>
                </div>
            </div>
            
            <h3>Per-Entry Buttons</h3>
            <div class="component-demo">
                <div style="display: flex; gap: 12px;">
                    <button class="btn btn-secondary entry-expand-btn">expand &#9660;</button>
                    <button class="btn btn-secondary entry-expand-btn">collapse &#9650;</button>
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

        <!-- Magnitu: Score Badges -->
        <section class="styleguide-section">
            <h2>Magnitu: Score Badges</h2>
            <p>Small inline badge in <code>.entry-header</code>, pushed right via <code>margin-left: auto</code>. Shows relevance score (0–100). Color encodes the predicted label. Uses <code>.magnitu-badge</code> + variant class.</p>

            <h3>Badge Variants</h3>
            <div class="component-demo">
                <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                    <span class="magnitu-badge magnitu-badge-investigation" title="investigation_lead (92%)">92</span>
                    <span style="font-size: 12px;">investigation_lead</span>
                </div>
                <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin-top: 8px;">
                    <span class="magnitu-badge magnitu-badge-important" title="important (74%)">74</span>
                    <span style="font-size: 12px;">important</span>
                </div>
                <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin-top: 8px;">
                    <span class="magnitu-badge magnitu-badge-background" title="background (45%)">45</span>
                    <span style="font-size: 12px;">background</span>
                </div>
                <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin-top: 8px;">
                    <span class="magnitu-badge magnitu-badge-noise" title="noise (12%)">12</span>
                    <span style="font-size: 12px;">noise</span>
                </div>
            </div>

            <h3>Badge in Card Header</h3>
            <div class="component-demo">
                <div class="entry-card" style="max-width: 500px;">
                    <div class="entry-header">
                        <span class="entry-tag" style="background-color: #add8e6;">politics</span>
                        <span class="magnitu-badge magnitu-badge-investigation" title="investigation_lead (92%)">92</span>
                    </div>
                    <h3 class="entry-title"><a href="#">High-score entry with badge</a></h3>
                    <div class="entry-content entry-preview">Tag on the left, score badge pushed right via margin-left: auto.</div>
                    <div class="entry-actions">
                        <span></span>
                        <span class="entry-date">22.02.2026 10:00</span>
                    </div>
                </div>
            </div>

            <h3>Badge Colors</h3>
            <div class="component-demo">
                <div>
                    <div class="color-swatch" style="background-color: #FF6B6B;">
                        <div class="color-swatch-info">#FF6B6B<br>Investigation</div>
                    </div>
                    <div class="color-swatch" style="background-color: #FFA94D;">
                        <div class="color-swatch-info">#FFA94D<br>Important</div>
                    </div>
                    <div class="color-swatch" style="background-color: #74C0FC;">
                        <div class="color-swatch-info">#74C0FC<br>Background</div>
                    </div>
                    <div class="color-swatch" style="background-color: #e0e0e0;">
                        <div class="color-swatch-info">#e0e0e0<br>Noise</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Magnitu: Alert Card -->
        <section class="styleguide-section">
            <h2>Magnitu: Alert Card</h2>
            <p>High-score entries can be highlighted with coral border and shadow via <code>.entry-card.magnitu-alert</code>. Used for investigation leads that exceed the alert threshold.</p>

            <div class="component-demo">
                <div class="entry-card magnitu-alert" style="max-width: 500px;">
                    <div class="entry-header">
                        <span class="entry-tag" style="background-color: #add8e6;">investigation</span>
                        <span class="magnitu-badge magnitu-badge-investigation">95</span>
                    </div>
                    <h3 class="entry-title"><a href="#">Alert-highlighted entry</a></h3>
                    <div class="entry-content entry-preview">Coral (#FF6B6B) border + 3px shadow. On hover, shadow grows to 4px.</div>
                    <div class="entry-actions">
                        <span></span>
                        <span class="entry-date">22.02.2026 10:00</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Magnitu: Explanation Panel -->
        <section class="styleguide-section">
            <h2>Magnitu: Explanation Panel</h2>
            <p>"Why?" button toggles a feature explanation panel inside the card. Button uses <code>.btn.magnitu-why-btn</code> (compact, same as expand). Panel uses <code>.magnitu-explanation</code>. Feature chips show positive (green) and negative (red) weights.</p>

            <h3>Why? Button</h3>
            <div class="component-demo">
                <div style="display: flex; gap: 12px;">
                    <button class="btn btn-secondary magnitu-why-btn">Why?</button>
                    <button class="btn btn-secondary magnitu-why-btn">Hide</button>
                </div>
            </div>

            <h3>Explanation Panel (expanded)</h3>
            <div class="component-demo">
                <div class="entry-card" style="max-width: 500px;">
                    <div class="entry-header">
                        <span class="entry-tag" style="background-color: #add8e6;">politics</span>
                        <span class="magnitu-badge magnitu-badge-investigation">88</span>
                    </div>
                    <h3 class="entry-title"><a href="#">Entry with visible explanation</a></h3>
                    <div class="entry-content entry-preview">Preview text shown here...</div>
                    <div class="magnitu-explanation" style="display: block;">
                        <div class="magnitu-explanation-label">Magnitu: investigation_lead (88% confidence)</div>
                        <div class="magnitu-explanation-features">
                            <span class="magnitu-feature magnitu-feature-positive">parliament +0.42</span>
                            <span class="magnitu-feature magnitu-feature-positive">legislation +0.31</span>
                            <span class="magnitu-feature magnitu-feature-positive">regulation +0.18</span>
                            <span class="magnitu-feature magnitu-feature-negative">sport -0.22</span>
                            <span class="magnitu-feature magnitu-feature-negative">entertainment -0.15</span>
                        </div>
                    </div>
                    <div class="entry-actions">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <button class="btn btn-secondary entry-expand-btn">expand &#9660;</button>
                            <button class="btn btn-secondary magnitu-why-btn">Hide</button>
                        </div>
                        <span class="entry-date">22.02.2026 10:00</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Score Coverage & Sort Toggle -->
        <section class="styleguide-section">
            <h2>Score Coverage &amp; Sort Toggle</h2>
            <p>Section title row shows score coverage (<code>.magnitu-coverage</code>) and a sort toggle button. Sort buttons reuse the <code>.btn.btn-secondary.entry-expand-all-btn</code> compact style.</p>

            <h3>Score Coverage in Title</h3>
            <div class="component-demo">
                <div class="section-title-row">
                    <h2 class="section-title" style="margin-bottom: 0;">
                        Refreshed: 22.02.2026 10:00
                        <span class="magnitu-coverage">&middot; 142 of 200 scored</span>
                    </h2>
                    <div style="display: flex; gap: 6px; align-items: center;">
                        <button class="btn btn-secondary entry-expand-all-btn">Sort by Date</button>
                        <button class="btn btn-secondary entry-expand-all-btn">expand all &#9660;</button>
                    </div>
                </div>
            </div>

            <h3>Day Grouping (Magnitu page)</h3>
            <div class="component-demo">
                <div class="section-title-row">
                    <h2 class="section-title" style="margin-bottom: 0;">Today <span style="font-weight: 400; font-size: 13px;">(5)</span></h2>
                    <button class="btn btn-secondary entry-expand-all-btn">expand all &#9660;</button>
                </div>
                <div class="section-title-row" style="margin-top: 16px;">
                    <h2 class="section-title" style="margin-bottom: 0;">Yesterday <span style="font-weight: 400; font-size: 13px;">(12)</span></h2>
                    <button class="btn btn-secondary entry-expand-all-btn">expand all &#9660;</button>
                </div>
            </div>
        </section>

        <!-- Search Drawer -->
        <section class="styleguide-section">
            <h2>Search Drawer</h2>
            <p>Opens below top bar via search icon button. Uses <code>.search-drawer</code> (hidden by default, <code>.open</code> shows it). Contains a <code>.search-form</code> with search input and buttons.</p>

            <div class="component-demo">
                <div class="search-drawer" style="display: block; margin-top: 0; margin-bottom: 0;">
                    <form class="search-form" onsubmit="return false;">
                        <input type="search" placeholder="Search entries..." class="search-input" style="min-width: 0;">
                        <button type="button" class="btn btn-primary">Search</button>
                        <a href="#" class="btn btn-secondary">Clear</a>
                    </form>
                </div>
            </div>
        </section>

        <!-- Active States -->
        <section class="styleguide-section">
            <h2>Active States</h2>
            <p>Three patterns for showing selected/active state: tag filter pills (multi-select), category buttons (single-select), and nav links (page-specific color).</p>

            <h3>Tag Filter Pill: Active</h3>
            <p>Active pills get <code>.tag-filter-pill-active</code> + inline <code>background-color</code> matching source type. Inactive pills stay white. An "All/None" toggle pill selects or deselects all checkboxes.</p>
            <div class="component-demo">
                <div class="tag-filter-list">
                    <button type="button" class="tag-filter-pill" style="cursor: pointer;"><span>All</span></button>
                    <label class="tag-filter-pill tag-filter-pill-active" style="background-color: #add8e6;">
                        <input type="checkbox" checked>
                        <span>RSS Active</span>
                    </label>
                    <label class="tag-filter-pill">
                        <input type="checkbox">
                        <span>RSS Inactive</span>
                    </label>
                    <label class="tag-filter-pill tag-filter-pill-active" style="background-color: #FFDBBB;">
                        <input type="checkbox" checked>
                        <span>Email Active</span>
                    </label>
                    <label class="tag-filter-pill tag-filter-pill-active" style="background-color: #C5B4D1;">
                        <input type="checkbox" checked>
                        <span>Substack Active</span>
                    </label>
                    <label class="tag-filter-pill tag-filter-pill-active" style="background-color: #f5f562;">
                        <input type="checkbox" checked>
                        <span>🇪🇺 EU Lex</span>
                    </label>
                    <label class="tag-filter-pill">
                        <input type="checkbox">
                        <span>🇨🇭 CH Lex</span>
                    </label>
                    <label class="tag-filter-pill tag-filter-pill-active" style="background-color: #FFDBBB;">
                        <input type="checkbox" checked>
                        <span>🌐 Scraper</span>
                    </label>
                </div>
            </div>

            <h3>Category Button: Active</h3>
            <p>Single-select. Active button gets inline <code>background-color</code> matching source. Default active state is not in CSS — applied via inline style per page.</p>
            <div class="component-demo">
                <div class="category-filter">
                    <a href="#" class="category-btn" style="background-color: #add8e6;">All</a>
                    <a href="#" class="category-btn">Category 1</a>
                    <a href="#" class="category-btn">Category 2</a>
                </div>
            </div>

            <h3>Nav Link: Colored Active</h3>
            <p>Default <code>.nav-link.active</code> is black bg / white text. Source pages override with inline color. Feed page uses default black.</p>
            <div class="component-demo">
                <nav class="nav-drawer" style="display: flex;">
                    <a href="#" class="nav-link active">Feed (default)</a>
                    <a href="#" class="nav-link active" style="background-color: #add8e6; color: #000000;">RSS</a>
                    <a href="#" class="nav-link active" style="background-color: #f5f562; color: #000000;">Lex / Jus</a>
                    <a href="#" class="nav-link active" style="background-color: #FFDBBB; color: #000000;">Mail / Scraper</a>
                    <a href="#" class="nav-link active" style="background-color: #C5B4D1; color: #000000;">Substack</a>
                </nav>
            </div>
        </section>

        <!-- Empty State -->
        <section class="styleguide-section">
            <h2>Empty State</h2>
            <p>Centered placeholder when no entries are available. Uses <code>.empty-state</code>, 12px small text, 30px padding.</p>

            <div class="component-demo">
                <div class="empty-state">
                    <p>No entries available yet. Add feeds to see entries here.</p>
                </div>
            </div>
        </section>

        <!-- Jus Card -->
        <section class="styleguide-section">
            <h2>Jus Card (Case Law)</h2>
            <p>Swiss case law entries use the standard <code>.entry-card</code> with source tag (⚖️), document type tag, monospace case number, and a source-specific link label. No expand/collapse — titles are usually short.</p>

            <div class="component-demo">
                <div class="entry-card">
                    <div class="entry-header">
                        <span class="entry-tag" style="background-color: #f5f562; border-color: #000000;">⚖️ BGer</span>
                        <span class="entry-tag" style="background-color: #f5f5f5;">Entscheid</span>
                    </div>
                    <h3 class="entry-title">
                        <a href="#">Beschwerde betreffend Steuerrecht</a>
                    </h3>
                    <div class="entry-actions">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-family: monospace; font-size: 12px; font-weight: 600;">7B 835/2025</span>
                            <a href="#" class="entry-link">Entscheid &rarr;</a>
                        </div>
                        <span class="entry-date">15.01.2026</span>
                    </div>
                </div>
            </div>

            <div class="component-demo">
                <div class="entry-card">
                    <div class="entry-header">
                        <span class="entry-tag" style="background-color: #f5f562; border-color: #000000;">⚖️ BVGer</span>
                        <span class="entry-tag" style="background-color: #f5f5f5;">Urteil</span>
                    </div>
                    <h3 class="entry-title">
                        <a href="#">Verfügung betreffend Asylverfahren</a>
                    </h3>
                    <div class="entry-actions">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-family: monospace; font-size: 12px; font-weight: 600;">A-6740/2023</span>
                            <a href="#" class="entry-link">Urteil &rarr;</a>
                        </div>
                        <span class="entry-date">03.01.2024</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Scraper Card -->
        <section class="styleguide-section">
            <h2>Scraper Card</h2>
            <p>Web scraper entries use <code>.entry-card</code> with 🌐 emoji source tag (#FFDBBB). Content preview shows "Open page →". A compact "delete" button (inline padding 2px 8px, 11px font) hides entries.</p>

            <div class="component-demo">
                <div class="entry-card">
                    <div class="entry-header">
                        <span class="entry-tag" style="background-color: #FFDBBB; border-color: #000000;">🌐 Example Site</span>
                        <span class="magnitu-badge magnitu-badge-important" title="important (74%)">74</span>
                    </div>
                    <h3 class="entry-title">
                        <a href="#">Scraped page title example</a>
                    </h3>
                    <p style="font-size: 12px; color: #000000; line-height: 1.5; margin-bottom: 8px;">
                        Content preview from the scraped page, truncated to 300 characters...
                        <a href="#" class="entry-link">Open page &rarr;</a>
                    </p>
                    <div class="entry-actions">
                        <button type="button" class="btn btn-secondary" style="padding: 2px 8px; font-size: 11px;">delete</button>
                        <span class="entry-date">20.02.2026 14:30</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Settings: Tab Bar -->
        <section class="styleguide-section">
            <h2>Settings: Tab Bar</h2>
            <p>Settings pages use <code>.tag-filter-pill</code> as link tabs inside <code>.tag-filter-list</code>. Active tab gets inline <code>background-color</code> matching the section's brand color. Uses <code>&lt;a&gt;</code> instead of <code>&lt;label&gt;</code>.</p>

            <div class="component-demo">
                <div class="tag-filter-list">
                    <a href="#" class="tag-filter-pill" style="text-decoration: none; background-color: #add8e6;">Basic</a>
                    <a href="#" class="tag-filter-pill" style="text-decoration: none;">Script</a>
                    <a href="#" class="tag-filter-pill" style="text-decoration: none;">Lex</a>
                    <a href="#" class="tag-filter-pill" style="text-decoration: none;">Magnitu</a>
                    <a href="#" class="tag-filter-pill" style="text-decoration: none;">Styleguide</a>
                </div>
            </div>
        </section>

        <!-- Settings: Section & List -->
        <section class="styleguide-section">
            <h2>Settings: Section &amp; List</h2>
            <p>Settings sections use <code>.settings-section</code> (border-bottom, margin-bottom). Each item in <code>.settings-list</code> uses <code>.settings-item</code> with <code>.settings-item-info</code> (title + meta) on the left and <code>.settings-item-actions</code> (buttons) on the right. Items have 1px border (lighter than cards).</p>

            <div class="component-demo">
                <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #000000;">
                    <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 16px;">RSS Feeds</h2>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <div style="border: 1px solid #000000; padding: 12px 16px; background-color: #ffffff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <div style="flex: 1; min-width: 200px;">
                                <div style="font-size: 14px; font-weight: 700; margin-bottom: 4px;">Example Feed</div>
                                <div style="font-size: 12px; color: #000000; margin-bottom: 4px;">https://example.com/feed.xml</div>
                                <div style="display: inline-block; padding: 4px 12px; background-color: #f5f5f5; border: 1px solid #000000; font-size: 12px; font-weight: 600; margin-top: 8px;">politics</div>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <a href="#" class="btn btn-secondary">Refresh</a>
                                <a href="#" class="btn btn-danger">Delete</a>
                            </div>
                        </div>
                        <div style="border: 1px solid #000000; padding: 12px 16px; background-color: #ffffff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <div style="flex: 1; min-width: 200px;">
                                <div style="font-size: 14px; font-weight: 700; margin-bottom: 4px;">Another Feed</div>
                                <div style="font-size: 12px; color: #000000; margin-bottom: 4px;">https://another.com/rss</div>
                                <div style="display: inline-block; padding: 4px 12px; background-color: #f5f5f5; border: 1px solid #000000; font-size: 12px; font-weight: 600; margin-top: 8px;">economy</div>
                            </div>
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <a href="#" class="btn btn-secondary">Refresh</a>
                                <a href="#" class="btn btn-danger">Delete</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Settings: Colored Section Heading -->
        <section class="styleguide-section">
            <h2>Settings: Colored Section Heading</h2>
            <p>Some settings sections (like Magnitu) use an inline <code>background-color</code> on the heading for emphasis. The heading gets padding and <code>display: inline-block</code>.</p>

            <div class="component-demo">
                <h2 style="background-color: #FF6B6B; padding: 8px 14px; display: inline-block; font-size: 18px; font-weight: 700; margin-bottom: 0;">Magnitu</h2>
            </div>
        </section>

        <!-- Circuit Breaker Alert -->
        <section class="styleguide-section">
            <h2>Circuit Breaker Alert</h2>
            <p>Warning banner shown when sources have been auto-paused after repeated failures. Yellow background (#fff3cd), 2px black border. Inline pattern — no reusable class.</p>

            <div class="component-demo">
                <div style="background: #fff3cd; border: 2px solid #000; padding: 10px 14px; font-size: 13px;">
                    <strong>Circuit Breaker</strong> — Some sources have been automatically paused after 3+ consecutive failures.
                    <div style="margin-top: 6px;">
                        <strong>Example Feed</strong>
                        <span style="color: #666;">(5 failures)</span>
                        — <code style="font-size: 11px;">HTTP 503 Service Unavailable</code>
                        <a href="#" style="margin-left: 4px; font-size: 11px;">retry</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Model Version Badge -->
        <section class="styleguide-section">
            <h2>Model Version Badge</h2>
            <p>Shown on the Magnitu page below the top bar. Model name in bold, version in a small coral badge with 2px border.</p>

            <div class="component-demo">
                <div style="font-size: 12px; color: #000000;">
                    <strong>seismo_distiller_v3</strong>
                    <span style="font-size: 11px; font-weight: 600; padding: 1px 6px; border: 2px solid #000000; background: #FF6B6B;">v2.1</span>
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
            if (btn) btn.textContent = 'expand \u25BC';
        }

        function expandEntry(card, btn) {
            var preview = card.querySelector('.entry-preview');
            var full = card.querySelector('.entry-full-content');
            if (!preview || !full) return;
            preview.style.display = 'none';
            full.style.display = 'block';
            if (btn) btn.textContent = 'collapse \u25B2';
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
            btn.textContent = !isExpanded ? 'collapse all \u25B2' : 'expand all \u25BC';
        });

        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.magnitu-why-btn');
            if (!btn) return;
            var card = btn.closest('.entry-card');
            if (!card) return;
            var explanation = card.querySelector('.magnitu-explanation');
            if (!explanation) return;
            if (explanation.style.display === 'block') {
                explanation.style.display = 'none';
                btn.textContent = 'Why?';
            } else {
                explanation.style.display = 'block';
                btn.textContent = 'Hide';
            }
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
