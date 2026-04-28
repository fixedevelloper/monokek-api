<div class='card text-center'>
    <div class='progress-container'>
        <div class='progress-bar' style='width:16%'></div>
    </div>

    <div class="welcome-icon" style="font-size: 50px; margin-bottom: 20px;">
        🚀
    </div>

    <h1>Bienvenue</h1>
    <p>Cet assistant va vous guider dans l'installation et la configuration de votre application Laravel en quelques minutes.</p>
    
    <div class="info-box">
        <p>Avant de commencer, assurez-vous d'avoir :</p>
        <ul style="text-align: left; display: inline-block;">
            <li>Une base de données MySQL vide</li>
            <li>Les droits d'écriture sur le fichier .env</li>
            <li>Votre clé de licence</li>
        </ul>
    </div>

    <div class="actions">
        <a class='btn primary' href='?step=server'>Commencer l'installation</a>
    </div>
</div>

<style>
/* Style spécifique pour la barre de progression */
.progress-container {
    background-color: #e5e7eb;
    border-radius: 10px;
    height: 8px;
    width: 100%;
    margin-bottom: 30px;
    overflow: hidden;
}

.progress-bar {
    background-color: #4f46e5;
    height: 100%;
    transition: width 0.3s ease;
}

.text-center {
    text-align: center;
}

.info-box {
    background: #f3f4f6;
    padding: 15px;
    border-radius: 8px;
    margin: 25px 0;
    color: #4b5563;
}
</style>