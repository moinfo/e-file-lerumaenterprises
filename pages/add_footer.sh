#!/bin/bash

# CSS to add
CSS='
    :root {
        --primary-orange: #f08c00;
        --light-orange: #ffa726;
        --text-muted: #b0b0b0;
    }

    body {
        padding-bottom: 120px;
    }

    .footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        color: var(--text-muted);
        padding: 1rem 2rem;
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
        border-top: 2px solid var(--primary-orange);
        z-index: 998;
        backdrop-filter: blur(10px);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        font-size: 0.9rem;
    }

    .footer-left,
    .footer-center,
    .footer-right {
        flex: 1;
        min-width: 200px;
        padding: 0.5rem;
    }

    .footer-left {
        text-align: left;
        font-weight: 600;
        color: var(--primary-orange);
    }

    .footer-center {
        text-align: center;
    }

    .footer-center span {
        display: inline-block;
        margin: 0.2rem 0;
    }

    .footer-center a {
        color: var(--light-orange);
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .footer-center a:hover {
        color: var(--primary-orange);
    }

    .footer-right {
        text-align: right;
    }

    .footer-right a {
        color: var(--text-muted);
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .footer-right a:hover {
        color: var(--primary-orange);
    }

    .footer-icons {
        margin-top: 0.5rem;
    }

    .footer-icons a {
        color: var(--text-muted);
        margin: 0 0.5rem;
        font-size: 1.1rem;
        transition: all 0.3s ease;
    }

    .footer-icons a:hover {
        color: var(--primary-orange);
        transform: translateY(-2px);
    }

    @media (max-width: 768px) {
        .footer {
            padding: 1rem;
            font-size: 0.8rem;
        }

        .footer-left,
        .footer-center,
        .footer-right {
            flex: 100%;
            text-align: center;
            min-width: 100%;
            padding: 0.3rem;
        }

        .footer-icons {
            margin-top: 0.3rem;
        }

        .footer-icons a {
            margin: 0 0.3rem;
            font-size: 1rem;
        }
    }
'

# HTML Footer
FOOTER='
<footer class="footer">
    <div class="footer-left">
        <span>Version 3.0</span>
    </div>
    <div class="footer-center">
        <span>&copy; <?php echo date('\''Y'\''); ?> File Bridge System. All rights reserved.</span><br>
        <span>Powered By <a href="https://moinfo.co.tz" target="_blank">MoinfoTech Company Limited</a></span>
        <div class="footer-icons">
            <a href="#" title="Documentation"><i class="fas fa-book"></i></a>
            <a href="https://moinfo.co.tz" target="_blank" title="Support"><i class="fas fa-headset"></i></a>
            <a href="#" title="Settings"><i class="fas fa-cog"></i></a>
        </div>
    </div>
    <div class="footer-right">
        <a href="#">Terms of Service</a> | <a href="#">Privacy Policy</a>
    </div>
</footer>'

# Files to process (excluding already done and utility files)
FILES=(
    "database_record_cleanup.php"
    "document_types.php"
    "editor.php"
    "folders.php"
    "incoming_system_uploads.php"
    "search.php"
    "settings.document_folders.php"
    "settings.document_sub_folders.php"
    "settings.php"
    "settings.users.folder_manage_access.php"
    "settings.users.manageaccess.php"
    "settings.users.users.php"
    "settings_document_types.php"
    "settings_edited_files.php"
    "settings_uploads.php"
    "settings_users.php"
    "sub_folders.php"
    "uploads.php"
)

for file in "${FILES[@]}"; do
    filepath="/Applications/XAMPP/xamppfiles/htdocs/e-file/pages/$file"

    # Check if file exists
    if [ ! -f "$filepath" ]; then
        echo "File not found: $filepath"
        continue
    fi

    # Check if footer already exists
    if grep -q "Version 3.0" "$filepath" || grep -q "footer-left" "$filepath"; then
        echo "Footer already exists in: $file (skipped)"
        continue
    fi

    # Add CSS before closing </style> tag
    if grep -q "</style>" "$filepath"; then
        # Use sed to add CSS before </style>
        sed -i.bak "/<\/style>/i\\
$CSS" "$filepath"
        echo "Added CSS to: $file"
    else
        echo "No </style> tag found in: $file (skipped CSS)"
    fi

    # Add footer at the end of file
    echo "$FOOTER" >> "$filepath"
    echo "Added footer HTML to: $file"
done

echo "Footer addition complete!"
