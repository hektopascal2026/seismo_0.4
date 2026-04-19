<?php
/**
 * views/partials/settings_tab_llm.php
 *
 * LLM tools tab. Currently hosts the AI View Generator (previously at
 * ?action=beta). As more LLM-adjacent helpers land they can slot in here.
 */
?>
<p style="font-size: 12px; margin-bottom: 16px;">
    LLM-adjacent tools. Right now this houses the AI View Generator &mdash; a unified feed that rolls up entries across all sources into a single AI-readable page.
</p>

<section class="settings-section" id="ai-view-generator">
    <h2 style="background-color: #d4e9ff; padding: 8px 14px; display: inline-block;">AI View Generator</h2>
    <p style="font-size: 12px; margin: 8px 0 12px 0;">Configure what data goes into the AI-readable unified feed, then generate it.</p>

    <form method="GET" action="" id="ai-generator-form">
        <input type="hidden" name="action" value="ai_view">

        <!-- Sources -->
        <div style="margin-bottom: 14px;">
            <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13px;">Sources</label>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php
                $sourceOptions = [
                    'rss' => 'RSS',
                    'substack' => 'Substack',
                    'email' => 'Email',
                    'lex' => 'Lex',
                    'fr' => 'FR Lex',
                    'parl_mm' => 'Parl MM',
                    'jus' => 'Jus',
                    'scraper' => 'Scraper',
                    'calendar' => 'Leg',
                ];
                foreach ($sourceOptions as $key => $label): ?>
                <label style="display: flex; align-items: center; gap: 4px; font-size: 12px; cursor: pointer;">
                    <input type="checkbox" name="sources[]" value="<?= $key ?>" checked>
                    <?= $label ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Date range -->
        <div style="margin-bottom: 14px;">
            <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13px;">Date range</label>
            <select name="since" style="padding: 6px 10px; border: 2px solid #000; font-family: inherit; font-size: 12px;">
                <option value="24h">Last 24 hours</option>
                <option value="3d">Last 3 days</option>
                <option value="7d" selected>Last 7 days</option>
                <option value="30d">Last 30 days</option>
                <option value="90d">Last 90 days</option>
                <option value="all">All time</option>
            </select>
        </div>

        <!-- Magnitu score filter -->
        <div style="margin-bottom: 14px;">
            <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13px;">Magnitu labels</label>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php
                $labelOptions = [
                    'investigation_lead' => 'Investigation Lead',
                    'important' => 'Important',
                    'background' => 'Background',
                    'noise' => 'Noise',
                    'unscored' => 'Unscored',
                ];
                foreach ($labelOptions as $key => $label): ?>
                <label style="display: flex; align-items: center; gap: 4px; font-size: 12px; cursor: pointer;">
                    <input type="checkbox" name="labels[]" value="<?= $key ?>" checked>
                    <?= $label ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Minimum score -->
        <div style="margin-bottom: 14px;">
            <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13px;">Minimum score <span style="font-weight: 400; font-size: 11px;">(0-100, leave empty for no filter)</span></label>
            <input type="number" name="min_score" min="0" max="100" placeholder="e.g. 50" style="width: 100px; padding: 6px 10px; border: 2px solid #000; font-family: inherit; font-size: 12px;">
        </div>

        <!-- Priority keywords -->
        <div style="margin-bottom: 14px;">
            <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13px;">Priority keywords <span style="font-weight: 400; font-size: 11px;">(comma-separated &mdash; matching entries are boosted to the top)</span></label>
            <input type="text" name="keywords" placeholder="e.g. regulation, investigation, compliance" style="width: 100%; max-width: 500px; padding: 6px 10px; border: 2px solid #000; font-family: inherit; font-size: 12px;">
        </div>

        <!-- Max entries -->
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 13px;">Max entries</label>
            <input type="number" name="limit" min="1" max="1000" value="100" style="width: 100px; padding: 6px 10px; border: 2px solid #000; font-family: inherit; font-size: 12px;">
        </div>

        <button type="submit" class="btn btn-primary">Generate AI View</button>
    </form>
</section>
