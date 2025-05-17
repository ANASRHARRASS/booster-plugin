# Booster Plugin Architecture

This document outlines the architecture of the Booster WordPress plugin using Mermaid diagrams.

## Test Diagram

```mermaid
graph TD
    A[Start] --> B[Process]
    B --> C[End]
```

## Detailed Class Structure

```mermaid
graph TD
    Booster[Booster] --> Loader[Booster_Loader]
    Booster --> Admin[Booster_Admin]
    Booster --> Public[Booster_Public]
```

> To view and interact with diagrams:
>
> 1. Open this file in VS Code
> 2. Press Ctrl+K V (or Cmd+K V on Mac) to open preview side-by-side
> 3. Click any diagram to enter interactive mode
> 4. In interactive mode:
>    - Use mouse wheel to zoom
>    - Drag to pan
>    - Double-click to reset view

## Class Structure

```mermaid
%%{init: { 'sequence': {'mirrorActors': false}} }%%
classDiagram
    direction TB
    class Booster {
        -string plugin_name
        -string version
        -Booster_Loader loader
        +__construct()
        +run()
        -load_dependencies()
        -set_locale()
        -define_hooks()
    }

    class Booster_Loader {
        -array actions
        -array filters
        +add_action()
        +add_filter()
        +run()
    }

    class Booster_Content_Manager {
        +create_posts()
        -process_content()
        -map_category_to_ids()
    }

    class Booster_AI {
        +rewrite_content()
        -process_text()
    }

    class Booster_Parser {
        +parse_api_response()
        +expand_content()
        -clean_content()
    }

    class Booster_Utils {
        +process_post()
        +cleanup_temp_file()
        +download_image()
    }

    class Booster_Logger {
        +log()
        +get_recent_logs()
        -get_level_icon()
    }

    class Booster_Admin {
        -string plugin_name
        -string version
        +register_hooks()
        +render_settings_page()
    }

    class Booster_Public {
        -string plugin_name
        -string version
        +enqueue_styles()
        +enqueue_scripts()
    }

    class Booster_Affiliate_Manager {
        +process_content()
        -insert_affiliate_links()
    }

    class Booster_Trend_Matcher {
        +get_trending_keywords()
        +calculate_match_score()
    }

    %% Relationships
    Booster --> Booster_Loader : uses
    Booster --> Booster_Admin : initializes
    Booster --> Booster_Public : initializes
    Booster_Content_Manager --> Booster_AI : uses
    Booster_Content_Manager --> Booster_Parser : uses
    Booster_Content_Manager --> Booster_Utils : uses
    Booster_Content_Manager --> Booster_Logger : uses
    Booster_Content_Manager --> Booster_Affiliate_Manager : uses
    Booster_Content_Manager --> Booster_Trend_Matcher : uses
    Booster_Admin --> Booster_Logger : uses
```

## Plugin Flow

```mermaid
%%{init: { 'flowchart': {'useMaxWidth': false}} }%%
flowchart TD
    subgraph Initialization
        A[WordPress Init] --> B[Load Booster Plugin]
        B --> C[Initialize Core Classes]
        C --> D[Register Hooks]
    end
    
    subgraph Setup
        D --> E[Setup Admin Interface]
        D --> F[Setup Public Interface]
        D --> G[Setup Cron Jobs]
    end
    
    subgraph Content Processing
        G --> H[Fetch Content]
        H --> I[Parse Content]
        I --> J[Process Content]
        J --> K[AI Rewrite]
        J --> L[Add Affiliate Links]
        J --> M[Check Trends]
        K & L & M --> N[Create Posts]
    end
    
    subgraph Admin
        E --> O[Settings Page]
        O --> P[Configure APIs]
        O --> Q[Set Content Rules]
        O --> R[View Logs]
    end
```

## Data Flow

```mermaid
%%{init: { 'sequence': {'actorMargin': 100, 'messageMargin': 40}} }%%
sequenceDiagram
    participant Cron
    participant ContentManager
    participant Parser
    participant AI
    participant AffiliateManager
    participant WordPress

    Cron->>ContentManager: Trigger content fetch
    ContentManager->>Parser: Get API response
    Parser-->>ContentManager: Parsed content items
    ContentManager->>AI: Request rewrite
    AI-->>ContentManager: Rewritten content
    ContentManager->>AffiliateManager: Process affiliate links
    AffiliateManager-->>ContentManager: Content with links
    ContentManager->>WordPress: Create/Update posts
```

## Development Workflow

### Adding New Features

```mermaid
%%{init: { 'flowchart': {'nodeSpacing': 50, 'rankSpacing': 50}} }%%
flowchart TB
    subgraph Planning
        Start[Start Development] --> A[Identify Feature Location]
        A --> B{New or Existing?}
    end
    
    subgraph New Component
        B -->|New| C[Create New PHP Class]
        C --> E[Add Class Properties]
        C --> F[Implement Methods]
        C --> G[Add Error Handling]
    end
    
    subgraph Existing Component
        B -->|Existing| D[Locate Existing Files]
        D --> H[Analyze Existing Code]
        D --> I[Plan Changes]
        D --> J[Add/Modify Methods]
    end
    
    subgraph Testing
        K[Write Unit Tests]
        L[Test in WordPress]
        M[Check Errors]
    end
    
    C --> Testing
    J --> Testing
    
    Testing --> N{Issues Found?}
    N -->|Yes| O[Fix Issues]
    O --> Testing
    N -->|No| P[Document Changes]
    
    subgraph Documentation
        P --> Q[Update README]
        P --> R[Update PHPDoc]
        P --> S[Update ARCHITECTURE.md]
    end
    
    Q & R & S --> End[Commit Changes]
```

### Directory Structure

```mermaid
%%{init: { 'flowchart': {'diagramPadding': 20}} }%%
flowchart LR
    subgraph Plugin Root
        direction TB
        Root[booster/] --> Includes[includes/]
        Root --> Admin[admin/]
        Root --> Public[public/]
    end
    
    subgraph Core Files
        direction TB
        Includes --> CoreClasses[Core Classes]
        Includes --> Utils[Utilities]
        CoreClasses --> CM[Content Manager]
        CoreClasses --> Parser[Parser]
        CoreClasses --> AI[AI Service]
    end
    
    subgraph Admin Files
        direction TB
        Admin --> CSS[CSS Files]
        Admin --> JS[JavaScript]
        Admin --> Views[View Files]
    end
    
    subgraph Public Files
        direction TB
        Public --> PublicCSS[CSS Files]
        Public --> PublicJS[JavaScript]
        Public --> PublicViews[View Files]
    end
    
    subgraph Conventions
        direction LR
        A[class-feature-name.php] --> B[feature-name.css]
        B --> C[feature-name.js]
    end
```

### Best Practices

```mermaid
%%{init: { 'flowchart': {'nodeSpacing': 30}} }%%
flowchart LR
    subgraph "Code Standards"
        direction TB
        A[PSR-12 Coding Style]
        B[WordPress Coding Standards]
        C[Type Declarations]
    end
    
    subgraph "Error Handling"
        direction TB
        D[Use Booster_Logger]
        E[Proper Exception Handling]
        F[Input Validation]
    end
    
    subgraph "Security"
        direction TB
        G[Data Sanitization]
        H[Capability Checks]
        I[Nonce Verification]
    end
    
    A & B & C --> J[Quality Code]
    D & E & F --> K[Reliable Code]
    G & H & I --> L[Secure Code]
