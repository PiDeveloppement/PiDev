# 📋 RECAP : IMPLÉMENTATION DU CODE QR - EventFlow

## 🎯 OBJECTIF GLOBAL
Générer des QR codes pour les tickets d'événements et les afficher dans la page de détail du billet.

---

## 📊 CONTEXTE ACTUEL

### État de l'application
- **Framework** : JavaFX (Maven)
- **BD** : MySQL avec Hibernate/JPA
- **Authentification** : OK ✅
- **Front office (vitrine)** : OK ✅
- **Back office (dashboard)** : OK ✅
- **Gestion des événements** : OK ✅

### Flux participant actuel
1. Participant voit les événements publics (front office)
2. Clique sur "Participer"
3. Si pas connecté → popup login/signup
4. Après connexion → redirect vers front office
5. Crée automatiquement un ticket (EventTicket)
6. Popup confirmation avec code du ticket
7. **MANQUANT** : Voir le billet avec QR code

---

## ✅ CE QUI EST DÉJÀ FAIT

### 1. **Table de base de données**
```sql
CREATE TABLE event_ticket (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ticket_code VARCHAR(50) UNIQUE,
  event_id INT,
  user_id INT,
  is_used BOOLEAN DEFAULT FALSE,
  used_at TIMESTAMP,
  qr_code VARCHAR(255),  -- ← Colonne pour stocker le chemin du QR
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES event(id),
  FOREIGN KEY (user_id) REFERENCES user(id_User)
);
```

### 2. **Modèle EventTicket.java**
- Classe avec tous les getters/setters
- Méthode `generateTicketCode(eventId, userId)` ✅
- Propriété `qrCode` pour stocker le chemin

### 3. **Fichiers FXML**
- `ticket-view.fxml` : Page affichage du billet avec ImageView `fx:id="qrImageView"` ✅
- `my-ticket.fxml` : Page alternative du billet (non utilisée)

### 4. **Contrôleurs**
- `LoginController.java` : Crée automatiquement un ticket après connexion ✅
- `EventsFrontController.java` : Bouton "Participer" → crée ticket ✅
- `EventTicketViewController.java` : Page affichage du billet (contient `qrCodeImage`) ✅

---

## 🔧 CE QU'IL FAUT FAIRE

### **ÉTAPE 1 : Générer le QR Code**

#### 1a. **Ajouter la dépendance Maven** (pom.xml)
```xml
<!-- QR Code generation -->
<dependency>
    <groupId>com.google.zxing</groupId>
    <artifactId>core</artifactId>
    <version>3.5.3</version>
</dependency>
<dependency>
    <groupId>com.google.zxing</groupId>
    <artifactId>javase</artifactId>
    <version>3.5.3</version>
</dependency>
```

#### 1b. **Méthode dans EventTicketService.java**
```java
// Après createTicket() ajouter :
private String generateAndSaveQrCode(String ticketCode, int ticketId) {
    // 1. Générer l'image QR en PNG (200x200)
    // 2. Créer le dossier /qrcodes s'il n'existe pas
    // 3. Sauvegarder dans : qrcodes/ticket_{ticketId}.png
    // 4. Retourner le chemin : "qrcodes/ticket_123.png"
}

private boolean updateTicketQRCode(int ticketId, String qrPath) {
    // UPDATE event_ticket SET qr_code = ? WHERE id = ?
}
```

#### 1c. **Modifier EventTicketService.createTicket()**
```java
public EventTicket createTicket(int eventId, int userId) {
    // ...existing code...
    
    if (rowsAffected > 0) {
        // ...get ticket ID from generated keys...
        
        // ✅ NOUVEAU : Générer et sauvegarder le QR
        String qrPath = generateAndSaveQrCode(ticketCode, ticket.getId());
        if (qrPath != null) {
            ticket.setQrCode(qrPath);
            updateTicketQRCode(ticket.getId(), qrPath);
        }
        
        return ticket;
    }
}
```

---

### **ÉTAPE 2 : Afficher le QR Code dans la page du billet**

#### 2a. **Modifier EventTicketViewController.java**
```java
private void displayTicket(EventTicket ticket) {
    // ...existing code (code, nom, email, etc)...
    
    // ✅ NOUVEAU : Charger et afficher le QR code
    if (ticket.getQrCode() != null && !ticket.getQrCode().isEmpty()) {
        try {
            String filePath = "file:" + new File(ticket.getQrCode()).getAbsolutePath();
            Image qrImage = new Image(filePath);
            qrCodeImage.setImage(qrImage);
            System.out.println("✅ QR code chargé : " + ticket.getQrCode());
        } catch (Exception e) {
            System.err.println("❌ Erreur chargement QR : " + e.getMessage());
            // Afficher un placeholder si erreur
        }
    }
}
```

---

### **ÉTAPE 3 : Intégrer dans le flux utilisateur**

#### 3a. **EventsFrontController.java**
Le bouton "Participer" crée déjà un ticket :
```java
private void createTicketForEvent(int eventId, String eventTitle) {
    // ...existing code...
    
    EventTicket ticket = ticketService.createTicket(eventId, userId);
    
    if (ticket != null) {
        // Afficher popup confirmation ✅
        // QR code est maintenant dans ticket.getQrCode()
        
        // Option 1 : Ajouter bouton "Voir mon billet" dans la popup
        // Option 2 : Redirect directement vers ticket-view.fxml
    }
}
```

#### 3b. **HelloApplication.java**
Garder la méthode existante :
```java
public static void loadEventDetailsPage(Event event) {
    // ...existing code...
}
```

---

## 📁 FICHIERS À MODIFIER

| Fichier | Action | Priorité |
|---------|--------|----------|
| `pom.xml` | Ajouter dépendance ZXing | 🔴 |
| `EventTicketService.java` | Ajouter génération QR | 🔴 |
| `EventTicketViewController.java` | Charger QR dans displayTicket() | 🔴 |
| `EventsFrontController.java` | Optionnel : améliorer popup | 🟡 |
| `LoginController.java` | Laisser tel quel | ✅ |
| `HelloApplication.java` | Laisser tel quel | ✅ |

---

## 🧪 WORKFLOW DE TEST

```
1. Lancer l'app
2. Landing page → Cliquer "Événements"
3. Voir la vitrine → Cliquer "Participer" sur un événement
4. Popup login → Se connecter (participant)
5. Popup confirmation → Voir le code du ticket
6. Cliquer "Voir mon billet" (à implémenter) ou naviguer vers ticket-view
7. ✅ Vérifier que le QR code s'affiche correctement
8. Scanner le QR code avec un téléphone → doit contenir le ticket_code
```

---

## 💾 PERSISTANCE DES DONNÉES

- QR code généré une seule fois à la création du ticket
- Chemin stocké dans colonne `qr_code` de la BD
- Si fichier supprimé → regenerer QR
- Dossier `/qrcodes` créé automatiquement

---

## 🚫 PIÈGES À ÉVITER

1. **Ne pas créer plusieurs QR codes** pour le même ticket
2. **Vérifier que le chemin du fichier est correct** (Windows vs Linux)
3. **Gérer le cas où le fichier QR n'existe pas** (afficher placeholder)
4. **Mettre à jour la BD** avec le chemin après génération
5. **Ne pas oublier de compiler avec Maven** avant de tester

---

## 📌 POINTS IMPORTANTS

- **API externe** : On n'utilise PAS QuickChart.io (problématique pour tests offline)
- **Libraire** : ZXing (Google) pour générer QR codes localement
- **Format QR** : PNG 200x200 pixels
- **Contenu QR** : Juste le `ticketCode` (ex: "EVT_001_USER_5")
- **UI** : ImageView dans ticket-view.fxml (déjà présent)

---

## ✅ CHECKLIST FINALE

- [ ] Ajouter dépendance Maven ZXing
- [ ] Implémenter `generateAndSaveQrCode()` dans EventTicketService
- [ ] Implémenter `updateTicketQRCode()` dans EventTicketService
- [ ] Modifier `createTicket()` pour appeler génération QR
- [ ] Modifier `displayTicket()` dans EventTicketViewController
- [ ] Compiler et tester
- [ ] Vérifier que QR code s'affiche dans ticket-view.fxml
- [ ] Scanner QR code avec téléphone pour vérifier contenu

---

## 📧 À PASSER À CLAUDE

Copie tout ce document et demande-lui :
> "Basé sur ce recap, implémente la génération et l'affichage des QR codes dans mon application EventFlow. Suis exactement les étapes, ajoute les dépendances, modifie les fichiers listés."

Il aura tout le contexte nécessaire ! 🎯

