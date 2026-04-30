<style>
    :root {
        --primary: #4f46e5;
        --primary-hover: #4338ca;
        --bg: #f8fafc;
        --card-bg: #ffffff;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --border: #e2e8f0;
        --info-bg: #eff6ff;
        --info-text: #1e40af;
        --info-border: #bfdbfe;
    }

    body {
        font-family: 'Inter', system-ui, sans-serif;
        background-color: var(--bg);
        color: var(--text-main);
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        margin: 0;
    }

    .card {
        background: var(--card-bg);
        padding: 3rem;
        border-radius: 1.25rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        width: 100%;
        max-width: 500px;
        border: 1px solid var(--border);
        text-align: center;
    }

    .welcome-icon {
        display: inline-block;
        background: #f1f5f9;
        width: 80px;
        height: 80px;
        line-height: 80px;
        border-radius: 50%;
        margin-bottom: 1.5rem;
        animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
        0% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
        100% { transform: translateY(0px); }
    }

    h1 {
        margin: 0 0 1rem 0;
        font-size: 2rem;
        font-weight: 800;
        color: var(--text-main);
        letter-spacing: -0.025em;
    }

    p {
        color: var(--text-muted);
        line-height: 1.6;
        font-size: 1.05rem;
        margin-bottom: 2rem;
    }

    .info-box {
        background-color: var(--info-bg);
        border: 1px solid var(--info-border);
        border-radius: 0.75rem;
        padding: 1.5rem;
        margin-bottom: 2.5rem;
        color: var(--info-text);
    }

    .info-box p {
        color: var(--info-text);
        font-weight: 600;
        margin-bottom: 0.75rem;
        font-size: 0.95rem;
    }

    .info-box ul {
        text-align: left;
        display: inline-block;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .info-box li {
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
    }

    .info-box li::before {
        content: "→";
        margin-right: 10px;
        font-weight: bold;
        opacity: 0.7;
    }

    .actions {
        margin-top: 1rem;
    }

    .btn.primary {
        display: block;
        background-color: var(--primary);
        color: white;
        padding: 1rem 2rem;
        border-radius: 0.75rem;
        font-size: 1.1rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
        box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
    }

    .btn.primary:hover {
        background-color: var(--primary-hover);
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
    }
</style>

<div class='card'>
    <div class="welcome-icon">
        🚀
    </div>

    <h1>Bienvenue</h1>
    <p>Cet assistant va vous guider dans l'installation et la configuration de votre application Laravel en quelques minutes.</p>
    
    <div class="info-box">
        <p>Avant de commencer, assurez-vous d'avoir :</p>
        <ul>
            <li>Une base de données MySQL vide</li>
            <li>Les droits d'écriture sur le fichier .env</li>
            <li>Votre clé de licence</li>
        </ul>
    </div>

    <div class="actions">
        <a class='btn primary' href='?step=server'>Commencer l'installation</a>
    </div>
</div>