package com.example.pidev;

import com.example.pidev.controller.dashboard.DashboardController;
import com.example.pidev.controller.event.*;
import com.example.pidev.controller.role.RoleController;
import com.example.pidev.controller.user.EditUserController;
import com.example.pidev.controller.user.ProfilController;
import com.example.pidev.controller.user.UserController;
import com.example.pidev.model.event.Event;
import com.example.pidev.model.event.EventCategory;
import com.example.pidev.model.event.EventTicket;
import com.example.pidev.model.role.Role;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Pos;
import javafx.scene.Node;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.input.KeyCode;
import javafx.scene.layout.HBox;
import javafx.scene.text.Text;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import javafx.scene.shape.Circle;
import javafx.stage.Modality;
import javafx.stage.Stage;
import com.example.pidev.utils.UserSession;
import com.example.pidev.model.user.UserModel;

import java.io.IOException;
import java.net.URL;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.HashMap;
import java.util.Map;

public class MainController {

    private static MainController instance;

    public MainController() {
        instance = this;
    }

    public static MainController getInstance() {
        return instance;
    }

    @FXML
    private VBox pageContentContainer;

    @FXML
    private Label pageTitle;

    @FXML
    private Label pageSubtitle;

    @FXML
    private Label navDateLabel;

    @FXML
    private Label navTimeLabel;

    @FXML
    private Label userNameLabel;

    @FXML
    private Text userInitialsText;

    @FXML
    private ImageView profileImageView;

    @FXML
    private Label userRoleLabel;

    @FXML
    private StackPane avatarContainer;

    @FXML
    private StackPane initialsContainer;

    @FXML
    private MenuButton profileMenu;

    // Boutons principaux de la sidebar
    @FXML
    private Button dashboardBtn;
    @FXML
    private Button eventsToggleBtn;
    @FXML
    private Button usersToggleBtn;
    @FXML
    private Button resourcesToggleBtn;
    @FXML
    private Button questionnairesToggleBtn;
    @FXML
    private Button sponsorsBtn;
    @FXML
    private Button budgetBtn;
    @FXML
    private Button settingsBtn;
    @FXML
    private Button logoutBtn;

    // Sous-menus Événements
    @FXML
    private VBox eventsSubmenu;
    @FXML
    private Button eventsListBtn;
    @FXML
    private Button categoriesBtn;
    @FXML
    private Button ticketsBtn;
    @FXML
    private Text eventsArrow;

    // Sous-menus Participants
    @FXML
    private VBox usersSubmenu;
    @FXML
    private Button rolesBtn;
    @FXML
    private Button inscriptionsBtn;
    @FXML
    private Text usersArrow;

    // Sous-menus Sponsors
    @FXML
    private VBox sponsorsSubmenu;
    @FXML
    private Button sponsorsListBtn;
    @FXML
    private Button contratsBtn;
    @FXML
    private Text sponsorsArrow;

    // Sous-menus Ressources
    @FXML
    private VBox resourcesSubmenu;
    @FXML
    private Button sallesBtn;
    @FXML
    private Button equipementsBtn;
    @FXML
    private Button reservationsBtn;
    @FXML
    private Text resourcesArrow;

    @FXML
    private TextField globalSearchField;

    // Sous-menus Questionnaires
    @FXML
    private VBox questionnairesSubmenu;
    @FXML
    private Button questionsBtn;
    @FXML
    private Button reponsesBtn;
    @FXML
    private Text questionnairesArrow;

    private final Map<String, PageInfo> pageInfoMap = new HashMap<>();
    private Button activeButton;

    // Référence au contrôleur du dashboard pour le rafraîchissement
    private DashboardController dashboardController;

    private static class PageInfo {
        String title;
        String subtitle;

        PageInfo(String title, String subtitle) {
            this.title = title;
            this.subtitle = subtitle;
        }
    }

    @FXML
    public void initialize() {
        System.out.println("✅ MainController initialisé");
        UserSession session = UserSession.getInstance();
        System.out.println("👤 Rôle connecté dans MainController: " + session.getRole());
        initializePageInfo();
        configureSidebarButtons();
        hideAllButtons();
        configureSidebarByRole();
        updateDateTime();
        loadUserProfileInHeader();
        setupGlobalSearch();

        // Charger le dashboard
        loadDashboardView();

        if (dashboardBtn != null) {
            setActiveButton(dashboardBtn);
        }
    }

    // ================= GESTION DES TOGGLES =================

    @FXML
    private void toggleEvents() {
        toggleSubmenu(eventsSubmenu, eventsArrow, eventsToggleBtn);
    }

    @FXML
    private void toggleUsers() {
        toggleSubmenu(usersSubmenu, usersArrow, usersToggleBtn);
    }

    @FXML
    private void toggleSponsors() {
        toggleSubmenu(sponsorsSubmenu, sponsorsArrow, sponsorsBtn);
    }

    @FXML
    private void toggleResources() {
        toggleSubmenu(resourcesSubmenu, resourcesArrow, resourcesToggleBtn);
    }

    @FXML
    private void toggleQuestionnaires() {
        toggleSubmenu(questionnairesSubmenu, questionnairesArrow, questionnairesToggleBtn);
    }

    private void toggleSubmenu(VBox submenu, Text arrow, Button active) {
        if (submenu == null) return;

        boolean isVisible = submenu.isVisible();
        submenu.setVisible(!isVisible);
        submenu.setManaged(!isVisible);

        if (arrow != null) arrow.setText(!isVisible ? "▼" : "▶");
        setActiveButton(active);
    }

    // ================= GESTION DU PROFIL =================

    private void loadUserProfileInHeader() {
        UserSession session = UserSession.getInstance();
        UserModel currentUser = session.getCurrentUser();

        if (currentUser != null) {
            if (userNameLabel != null) {
                userNameLabel.setText(session.getFullName());
            }

            if (userRoleLabel != null) {
                String roleName = session.getRole();
                userRoleLabel.setText(roleName);
            }

            String photoUrl = currentUser.getProfilePictureUrl();
            if (photoUrl != null && !photoUrl.isEmpty()) {
                try {
                    Image image = new Image(photoUrl, 28, 28, true, true);
                    profileImageView.setImage(image);
                    profileImageView.setVisible(true);

                    Circle clip = new Circle(14, 14, 14);
                    profileImageView.setClip(clip);

                    if (initialsContainer != null) {
                        initialsContainer.setVisible(false);
                    }

                    System.out.println("✅ Image de profil chargée");

                } catch (Exception e) {
                    System.err.println("Erreur chargement photo: " + e.getMessage());
                    showInitials(session.getInitials());
                }
            } else {
                showInitials(session.getInitials());
            }
        } else {
            if (userNameLabel != null) userNameLabel.setText("Invité");
            if (userRoleLabel != null) userRoleLabel.setText("Non connecté");
            showInitials("?");
        }
    }

    public void refreshHeaderProfile() {
        loadUserProfileInHeader();
    }

    private void showInitials(String initials) {
        if (profileImageView != null) {
            profileImageView.setVisible(false);
            profileImageView.setClip(null);
        }
        if (initialsContainer != null) {
            initialsContainer.setVisible(true);
            if (userInitialsText != null) {
                userInitialsText.setText(initials);
            }
        }
    }

    // ================= PAGE INFO =================

    private void initializePageInfo() {
        pageInfoMap.put("dashboard", new PageInfo("Tableau de bord", "Aperçu général de votre activité"));
        pageInfoMap.put("events", new PageInfo("Gestion des événements", "Consultez et gérez tous vos événements"));
        pageInfoMap.put("categories", new PageInfo("Gestion des catégories", "Gérez les catégories d'événements"));
        pageInfoMap.put("tickets", new PageInfo("Gestion des billets", "Gérez les billets et inscriptions"));
        pageInfoMap.put("users", new PageInfo("Gestion des participants", "Gérez les participants"));
        pageInfoMap.put("roles", new PageInfo("Gestion des rôles", "Gérez les différents rôles"));
        pageInfoMap.put("inscriptions", new PageInfo("Gestion des inscriptions", "Gérez les inscriptions"));
        pageInfoMap.put("sponsors", new PageInfo("Gestion des sponsors", "Gérez vos partenaires"));
        pageInfoMap.put("sponsorsList", new PageInfo("Liste des sponsors", "Consultez tous les sponsors"));
        pageInfoMap.put("contrats", new PageInfo("Gestion des contrats", "Gérez les contrats"));
        pageInfoMap.put("salles", new PageInfo("Gestion des salles", "Gérez les salles et espaces"));
        pageInfoMap.put("equipements", new PageInfo("Gestion des équipements", "Gérez le matériel"));
        pageInfoMap.put("reservations", new PageInfo("Gestion des réservations", "Gérez les réservations"));
        pageInfoMap.put("budget", new PageInfo("Gestion du budget", "Suivez vos finances"));

        // Ajout des entrées pour les questionnaires
        pageInfoMap.put("questions", new PageInfo("Gestion des questions", "Gérez les questions"));
        pageInfoMap.put("reponses", new PageInfo("Gestion des réponses", "Consultez les réponses"));
        pageInfoMap.put("resultats", new PageInfo("Résultats", "Statistiques et aperçu global"));
        pageInfoMap.put("historique", new PageInfo("Historique", "Consultation des anciens scores"));
        pageInfoMap.put("participantQuiz", new PageInfo("Passer le Quiz", "Interface d'examen"));

        pageInfoMap.put("settings", new PageInfo("Paramètres", "Configurez l'application"));
        pageInfoMap.put("profile", new PageInfo("Mon profil", "Consultez et modifiez vos informations"));
    }

    // ================= CONFIGURATION SIDEBAR =================

    private void configureSidebarButtons() {
        // Dashboard
        if (dashboardBtn != null) {
            dashboardBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(dashboardBtn);
                loadDashboardView();
            });
        }

        // Événements toggle
        if (eventsToggleBtn != null) {
            eventsToggleBtn.setOnAction(e -> {
                toggleEvents();
                collapseOtherSubmenus("events");
                setActiveButton(eventsToggleBtn);
            });
        }

        // Participants toggle
        if (usersToggleBtn != null) {
            usersToggleBtn.setOnAction(e -> {
                toggleUsers();
                collapseOtherSubmenus("users");
                setActiveButton(usersToggleBtn);
            });
        }

        // Sponsors toggle
        if (sponsorsBtn != null) {
            sponsorsBtn.setOnAction(e -> {
                toggleSponsors();
                collapseOtherSubmenus("sponsors");
                setActiveButton(sponsorsBtn);
            });
        }

        // Ressources toggle
        if (resourcesToggleBtn != null) {
            resourcesToggleBtn.setOnAction(e -> {
                toggleResources();
                collapseOtherSubmenus("resources");
                setActiveButton(resourcesToggleBtn);
            });
        }

        // Questionnaires toggle
        if (questionnairesToggleBtn != null) {
            questionnairesToggleBtn.setOnAction(e -> {
                toggleQuestionnaires();
                collapseOtherSubmenus("questionnaires");
                setActiveButton(questionnairesToggleBtn);
            });
        }

        // Sous-menus Événements
        if (eventsListBtn != null) {
            eventsListBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(eventsListBtn);
                showEventsList();
            });
        }

        if (categoriesBtn != null) {
            categoriesBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(categoriesBtn);
                showCategories();
            });
        }

        if (ticketsBtn != null) {
            ticketsBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(ticketsBtn);
                showTicketsList();
            });
        }

        // Sous-menus Participants
        if (rolesBtn != null) {
            rolesBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(rolesBtn);
                loadRoleView();
            });
        }

        if (inscriptionsBtn != null) {
            inscriptionsBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(inscriptionsBtn);
                loadUserView();
            });
        }

        // Sous-menus Sponsors
        if (sponsorsListBtn != null) {
            sponsorsListBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(sponsorsListBtn);
                loadSponsorsListView();
            });
        }

        if (contratsBtn != null) {
            contratsBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(contratsBtn);
                loadContratsView();
            });
        }

        // Budget
        if (budgetBtn != null) {
            budgetBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(budgetBtn);
                loadBudgetView();
            });
        }

        // Sous-menus Ressources
        if (sallesBtn != null) {
            sallesBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(sallesBtn);
                loadSallesView();
            });
        }

        if (equipementsBtn != null) {
            equipementsBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(equipementsBtn);
                loadEquipementsView();
            });
        }

        if (reservationsBtn != null) {
            reservationsBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(reservationsBtn);
                loadReservationsView();
            });
        }

        // ================= NOUVEAU: BOUTONS QUESTIONNAIRES =================
        if (questionsBtn != null) {
            questionsBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(questionsBtn);
                showQuestionEditor();
            });
        }

        if (reponsesBtn != null) {
            reponsesBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(reponsesBtn);
                showResultats();
            });
        }

        // Settings
        if (settingsBtn != null) {
            settingsBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(settingsBtn);
                loadSettingsView();
            });
        }

        // Logout
        if (logoutBtn != null) {
            logoutBtn.setOnAction(e -> logout());
        }
    }

    private void collapseOtherSubmenus(String currentMenu) {
        if (!"events".equals(currentMenu) && eventsSubmenu != null) {
            eventsSubmenu.setVisible(false);
            eventsSubmenu.setManaged(false);
            if (eventsArrow != null) eventsArrow.setText("▶");
        }
        if (!"users".equals(currentMenu) && usersSubmenu != null) {
            usersSubmenu.setVisible(false);
            usersSubmenu.setManaged(false);
            if (usersArrow != null) usersArrow.setText("▶");
        }
        if (!"sponsors".equals(currentMenu) && sponsorsSubmenu != null) {
            sponsorsSubmenu.setVisible(false);
            sponsorsSubmenu.setManaged(false);
            if (sponsorsArrow != null) sponsorsArrow.setText("▶");
        }
        if (!"resources".equals(currentMenu) && resourcesSubmenu != null) {
            resourcesSubmenu.setVisible(false);
            resourcesSubmenu.setManaged(false);
            if (resourcesArrow != null) resourcesArrow.setText("▶");
        }
        if (!"questionnaires".equals(currentMenu) && questionnairesSubmenu != null) {
            questionnairesSubmenu.setVisible(false);
            questionnairesSubmenu.setManaged(false);
            if (questionnairesArrow != null) questionnairesArrow.setText("▶");
        }
    }

    private void collapseAllSubmenus() {
        if (eventsSubmenu != null) {
            eventsSubmenu.setVisible(false);
            eventsSubmenu.setManaged(false);
            if (eventsArrow != null) eventsArrow.setText("▶");
        }
        if (usersSubmenu != null) {
            usersSubmenu.setVisible(false);
            usersSubmenu.setManaged(false);
            if (usersArrow != null) usersArrow.setText("▶");
        }
        if (sponsorsSubmenu != null) {
            sponsorsSubmenu.setVisible(false);
            sponsorsSubmenu.setManaged(false);
            if (sponsorsArrow != null) sponsorsArrow.setText("▶");
        }
        if (resourcesSubmenu != null) {
            resourcesSubmenu.setVisible(false);
            resourcesSubmenu.setManaged(false);
            if (resourcesArrow != null) resourcesArrow.setText("▶");
        }
        if (questionnairesSubmenu != null) {
            questionnairesSubmenu.setVisible(false);
            questionnairesSubmenu.setManaged(false);
            if (questionnairesArrow != null) questionnairesArrow.setText("▶");
        }
    }

    private void setActiveButton(Button button) {
        Button[] allButtons = {
                dashboardBtn, eventsToggleBtn, eventsListBtn, categoriesBtn, ticketsBtn,
                usersToggleBtn, rolesBtn, inscriptionsBtn,
                sponsorsBtn, sponsorsListBtn, contratsBtn,
                resourcesToggleBtn, sallesBtn, equipementsBtn, reservationsBtn,
                questionnairesToggleBtn, questionsBtn, reponsesBtn,
                budgetBtn, settingsBtn
        };

        for (Button btn : allButtons) {
            if (btn != null) {
                btn.getStyleClass().removeAll("main-menu-button", "submenu-button", "sidebar-button-active");

                boolean isSub = btn == eventsListBtn || btn == categoriesBtn || btn == ticketsBtn ||
                        btn == rolesBtn || btn == inscriptionsBtn ||
                        btn == sponsorsListBtn || btn == contratsBtn ||
                        btn == sallesBtn || btn == equipementsBtn || btn == reservationsBtn ||
                        btn == questionsBtn || btn == reponsesBtn || btn == budgetBtn;

                btn.getStyleClass().add(isSub ? "submenu-button" : "main-menu-button");
            }
        }

        if (button != null) {
            for (Button btn : allButtons) {
                if (btn != null) {
                    btn.getStyleClass().remove("sidebar-button-active");
                }
            }
            button.getStyleClass().add("sidebar-button-active");
        }
    }

    // ================= DATE =================

    private void updateDateTime() {
        LocalDateTime now = LocalDateTime.now();
        DateTimeFormatter dateFormatter = DateTimeFormatter.ofPattern("EEEE dd MMMM yyyy");
        DateTimeFormatter timeFormatter = DateTimeFormatter.ofPattern("HH:mm:ss");

        if (navDateLabel != null) navDateLabel.setText(now.format(dateFormatter));
        if (navTimeLabel != null) navTimeLabel.setText(now.format(timeFormatter));

        Thread dateThread = new Thread(() -> {
            while (true) {
                try {
                    Thread.sleep(1000);
                    javafx.application.Platform.runLater(() -> {
                        LocalDateTime currentTime = LocalDateTime.now();
                        if (navTimeLabel != null) {
                            navTimeLabel.setText(currentTime.format(timeFormatter));
                        }
                    });
                } catch (InterruptedException e) {
                    e.printStackTrace();
                }
            }
        });
        dateThread.setDaemon(true);
        dateThread.start();
    }

    // ================= CHARGEMENT DES PAGES =================

    public void loadPage(String fxmlPath, String pageKey) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource(fxmlPath));
            Parent page = loader.load();

            if ("profile".equals(pageKey) && loader.getController() instanceof ProfilController) {
                ((ProfilController) loader.getController()).setMainController(this);
            }

            // Si c'est le dashboard, garder une référence au contrôleur
            if ("dashboard".equals(pageKey) && loader.getController() instanceof DashboardController) {
                dashboardController = (DashboardController) loader.getController();
                dashboardController.setMainController(this);
            }

            PageInfo pageInfo = pageInfoMap.get(pageKey);
            if (pageInfo != null) {
                pageTitle.setText(pageInfo.title);
                pageSubtitle.setText(pageInfo.subtitle);
            }

            pageContentContainer.getChildren().setAll(page);

        } catch (IOException e) {
            e.printStackTrace();
            showSimpleAlert("Erreur", "Impossible de charger la page: " + fxmlPath);
        }
    }

    public void loadPage(String fxmlPath) {
        String pageKey = extractPageKeyFromPath(fxmlPath);
        loadPage(fxmlPath, pageKey);
    }

    private String extractPageKeyFromPath(String fxmlPath) {
        if (fxmlPath.contains("dashboard")) return "dashboard";
        if (fxmlPath.contains("event")) return "events";
        if (fxmlPath.contains("categorie")) return "categories";
        if (fxmlPath.contains("ticket")) return "tickets";
        if (fxmlPath.contains("user")) return "users";
        if (fxmlPath.contains("role")) return "roles";
        if (fxmlPath.contains("inscription")) return "inscriptions";
        if (fxmlPath.contains("salle")) return "salles";
        if (fxmlPath.contains("equipement")) return "equipements";
        if (fxmlPath.contains("reservation")) return "reservations";
        if (fxmlPath.contains("sponsor")) return "sponsors";
        if (fxmlPath.contains("sponsorsList")) return "sponsorsList";
        if (fxmlPath.contains("contrat")) return "contrats";
        if (fxmlPath.contains("budget")) return "budget";

        // Pour les questionnaires
        if (fxmlPath.contains("form_question")) return "questions";
        if (fxmlPath.contains("Resultat")) return "resultats";
        if (fxmlPath.contains("Participant")) return "participantQuiz";
        if (fxmlPath.contains("Historique")) return "historique";
        if (fxmlPath.contains("question")) return "questions";
        if (fxmlPath.contains("reponse")) return "reponses";

        if (fxmlPath.contains("settings")) return "settings";
        if (fxmlPath.contains("profil")) return "profile";
        if (fxmlPath.contains("editUser")) return "editUsers";
        if (fxmlPath.contains("editRole")) return "editRoles";
        return "dashboard";
    }

    // ================= MÉTHODES DE NAVIGATION =================

    public void loadDashboardView() {
        UserSession session = UserSession.getInstance();
        String role = session.getRole();

        if (role != null && (role.toLowerCase().contains("participant") ||
                role.toLowerCase().contains("default") ||
                role.toLowerCase().contains("invité") ||
                role.toLowerCase().contains("sponsor") ||
                role.toLowerCase().contains("organisateur") ||
                role.toLowerCase().contains("admin"))) {
            loadPage("/com/example/pidev/fxml/dashboard/dashboard.fxml", "dashboard");
        }
    }

    public void loadEventView() {
        loadPage("/com/example/pidev/fxml/event/event.fxml", "events");
    }

    public void loadCategoriesView() {
        loadPage("/com/example/pidev/fxml/event/categorie.fxml", "categories");
    }

    public void loadTicketsView() {
        loadPage("/com/example/pidev/fxml/event/ticket.fxml", "tickets");
    }

    public void loadUserView() {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/user/user.fxml")
            );
            Parent root = loader.load();
            UserController controller = loader.getController();
            controller.setMainController(this);

            updatePageHeader("users");

            pageContentContainer.getChildren().setAll(root);
        } catch (Exception e) {
            e.printStackTrace();
            showSimpleAlert("Erreur", "Impossible de charger la page des utilisateurs: " + e.getMessage());
        }
    }

    public void loadRoleView() {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/role/role.fxml")
            );
            Parent root = loader.load();

            RoleController controller = loader.getController();
            controller.setMainController(this);

            pageContentContainer.getChildren().setAll(root);
            updatePageHeader("roles");

        } catch (Exception e) {
            e.printStackTrace();
            showSimpleAlert("Erreur", "Impossible de charger la page des rôles: " + e.getMessage());
        }
    }

    public void loadEditUserView() {
        loadPage("/com/example/pidev/fxml/user/editUser.fxml", "editUsers");
    }

    public void loadSponsorView() {
        loadPage("/com/example/pidev/fxml/Sponsor/sponsor.fxml", "sponsors");
    }

    public void loadSponsorsListView() {
        loadPage("/com/example/pidev/fxml/sponsor/sponsorsList.fxml", "sponsorsList");
    }

    public void loadContratsView() {
        loadPage("/com/example/pidev/fxml/sponsor/contrat.fxml", "contrats");
    }

    public void loadBudgetView() {
        loadPage("/com/example/pidev/fxml/budget/budget.fxml", "budget");
    }

    // ================= MÉTHODES POUR LES RESSOURCES =================

    public void loadSallesView() {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/resource/salle.fxml")
            );
            Parent root = loader.load();

            pageContentContainer.getChildren().setAll(root);
            updatePageHeader("salles");

        } catch (Exception e) {
            e.printStackTrace();
            showSimpleAlert("Erreur", "Impossible de charger la page des salles: " + e.getMessage());
        }
    }

    public void loadEquipementsView() {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/resource/equipement.fxml")
            );
            Parent root = loader.load();

            pageContentContainer.getChildren().setAll(root);
            updatePageHeader("equipements");

        } catch (Exception e) {
            e.printStackTrace();
            showSimpleAlert("Erreur", "Impossible de charger la page des équipements: " + e.getMessage());
        }
    }

    public void loadReservationsView() {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/resource/reservation.fxml")
            );
            Parent root = loader.load();

            pageContentContainer.getChildren().setAll(root);
            updatePageHeader("reservations");

        } catch (Exception e) {
            e.printStackTrace();
            showSimpleAlert("Erreur", "Impossible de charger la page des réservations: " + e.getMessage());
        }
    }

    // ================= NOUVELLES MÉTHODES POUR LES QUESTIONNAIRES =================

    /**
     * Charge l'éditeur de questions
     */
    public void loadQuestionEditor() {
        loadQuestionnairePage("form_question.fxml", "Gestion des questions", "Gestion de la banque de données");
    }

    /**
     * Charge la page des résultats/statistiques
     */
    public void loadResultatsView() {
        loadQuestionnairePage("Resultat.fxml", "Résultats", "Statistiques et aperçu global");
    }

    /**
     * Charge la page du quiz participant
     */
    public void loadParticipantQuizView() {
        loadQuestionnairePage("Participant.fxml", "Passer le Quiz", "Interface d'examen");
    }

    /**
     * Charge la page d'historique
     */
    public void loadHistoriqueView() {
        loadQuestionnairePage("Historique.fxml", "Historique", "Consultation des anciens scores");
    }

    /**
     * Méthode générique pour charger les pages de questionnaire
     */
    private void loadQuestionnairePage(String fxmlFile, String title, String subtitle) {
        try {
            // Chemin vers les fichiers FXML du questionnaire
            String path = "/com/example/pidev/fxml/questionnaire/" + fxmlFile;
            URL fileUrl = getClass().getResource(path);

            if (fileUrl == null) {
                System.err.println("❌ Fichier FXML introuvable: " + path);
                showSimpleAlert("Erreur", "Fichier introuvable: " + fxmlFile);
                return;
            }

            FXMLLoader loader = new FXMLLoader(fileUrl);
            Parent root = loader.load();

            setContent(root);
            if (pageTitle != null) pageTitle.setText(title);
            if (pageSubtitle != null) pageSubtitle.setText(subtitle);

            System.out.println("✅ Page chargée: " + title);

        } catch (IOException e) {
            System.err.println("❌ Erreur chargement " + fxmlFile + ": " + e.getMessage());
            e.printStackTrace();
            showSimpleAlert("Erreur", "Impossible de charger la page: " + e.getMessage());
        }
    }

    // ================= ACTIONS FXML POUR QUESTIONNAIRES =================

    @FXML
    public void showQuestionEditor() {
        System.out.println("📝 Affichage de l'éditeur de questions");
        collapseAllSubmenus();
        setActiveButton(questionsBtn);
        loadQuestionEditor();
    }

    @FXML
    public void showResultats() {
        System.out.println("📊 Affichage des résultats");
        collapseAllSubmenus();
        setActiveButton(reponsesBtn);
        loadResultatsView();
    }

    @FXML
    public void showParticipantQuiz() {
        System.out.println("🎯 Affichage du quiz participant");
        collapseAllSubmenus();
        // Note: Vous pouvez ajouter un bouton spécifique si nécessaire
        loadParticipantQuizView();
    }

    @FXML
    public void showHistorique() {
        System.out.println("📜 Affichage de l'historique");
        collapseAllSubmenus();
        loadHistoriqueView();
    }

    // ================= MÉTHODES EXISTANTES =================

    public void loadQuestionsView() {
        showQuestionEditor();
    }

    public void loadReponsesView() {
        showResultats();
    }

    public void loadSettingsView() {
        loadPage("/com/example/pidev/fxml/settings/settings.fxml", "settings");
    }

    @FXML
    public void showProfile() {
        collapseAllSubmenus();
        loadPage("/com/example/pidev/fxml/user/profil.fxml", "profile");
    }

    @FXML
    public void showSettings() {
        collapseAllSubmenus();
        loadPage("/com/example/pidev/fxml/settings/settings.fxml", "settings");
    }

    public void loadEditRolePage(Role role) {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/role/editRole.fxml")
            );
            Parent root = loader.load();

            com.example.pidev.controller.role.EditRoleController controller = loader.getController();
            controller.setEditMode(role);
            controller.setMainController(this);

            pageContentContainer.getChildren().setAll(root);
            updatePageHeader("roles");

        } catch (Exception e) {
            e.printStackTrace();
            showSimpleAlert("Erreur", "Impossible de charger la page de modification: " + e.getMessage());
        }
    }

    public void loadAddRolePage() {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/role/editRole.fxml")
            );
            Parent root = loader.load();

            com.example.pidev.controller.role.EditRoleController controller = loader.getController();
            controller.setAddMode();
            controller.setMainController(this);

            pageContentContainer.getChildren().setAll(root);
            updatePageHeader("roles");

        } catch (Exception e) {
            e.printStackTrace();
            showSimpleAlert("Erreur", "Impossible de charger la page d'ajout: " + e.getMessage());
        }
    }

    @FXML
    public void logout() {
        try {
            if (dashboardController != null) {
                dashboardController.cleanup();
            }

            UserSession.getInstance().clearSession();
            Parent root = FXMLLoader.load(getClass().getResource("/com/example/pidev/fxml/auth/login.fxml"));
            Stage stage = HelloApplication.getPrimaryStage();
            stage.setScene(new Scene(root, 1200, 800));
            stage.setTitle("EventFlow - Connexion");
            stage.centerOnScreen();
        } catch (IOException e) {
            e.printStackTrace();
        }
    }

    public void refreshSidebarForRole() {
        System.out.println("🔄 Rafraîchissement de la sidebar");
        configureSidebarByRole();
        collapseAllSubmenus();
    }

    // ================= RECHERCHE GLOBALE =================

    private void setupGlobalSearch() {
        if (globalSearchField != null) {
            globalSearchField.setOnAction(event -> {
                String query = globalSearchField.getText().trim();
                if (!query.isEmpty()) {
                    performSimpleSearch(query);
                }
            });

            globalSearchField.setOnKeyPressed(event -> {
                if (event.getCode() == KeyCode.ESCAPE) {
                    globalSearchField.clear();
                }
            });
        }
    }

    private void performSimpleSearch(String query) {
        String lowerQuery = query.toLowerCase();
        ObservableList<String> results = FXCollections.observableArrayList();

        if ("dashboard".contains(lowerQuery)) results.add("📊 Dashboard");
        if ("événements".contains(lowerQuery) || "events".contains(lowerQuery)) {
            results.add("📅 Événements");
            results.add("   📋 Liste des événements");
            results.add("   🏷️ Catégories");
            results.add("   🎫 Billets");
        }
        if ("participants".contains(lowerQuery) || "users".contains(lowerQuery)) {
            results.add("👥 Participants");
            results.add("   👤 Rôles");
            results.add("   📝 Inscriptions");
        }
        if ("sponsors".contains(lowerQuery)) {
            results.add("💼 Sponsors");
            results.add("   📋 Liste sponsors");
            results.add("   📄 Contrats");
        }
        if ("ressources".contains(lowerQuery)) {
            results.add("💰 Ressources");
            results.add("   💵 Budget");
            results.add("   💻 Équipements");
            results.add("   🏢 Salles");
            results.add("   📅 Réservations");
        }
        if ("questionnaires".contains(lowerQuery)) {
            results.add("📝 Questionnaires");
            results.add("   ❓ Questions");
            results.add("   📊 Résultats");
            results.add("   📜 Historique");
            results.add("   🎯 Passer le Quiz");
        }
        if ("paramètres".contains(lowerQuery) || "settings".contains(lowerQuery)) {
            results.add("⚙️ Paramètres");
        }

        if (results.isEmpty()) {
            showSimpleAlert("Aucun résultat", "Aucun résultat trouvé pour: " + query);
        } else {
            showSimpleResultsPopup(query, results);
        }
    }

    private void showSimpleResultsPopup(String query, ObservableList<String> results) {
        Stage stage = new Stage();
        stage.setTitle("Résultats de recherche");
        stage.initModality(Modality.APPLICATION_MODAL);

        VBox root = new VBox(15);
        root.setStyle("-fx-padding: 20; -fx-background-color: white;");
        root.setPrefWidth(400);
        root.setMaxWidth(400);

        Label titleLabel = new Label("Résultats pour: \"" + query + "\"");
        titleLabel.setStyle("-fx-font-size: 14px; -fx-font-weight: bold; -fx-text-fill: #0D47A1;");

        ListView<String> listView = new ListView<>(results);
        listView.setPrefHeight(Math.min(300, results.size() * 40));
        listView.setStyle("-fx-background-color: transparent;");

        listView.setOnMouseClicked(event -> {
            String selected = listView.getSelectionModel().getSelectedItem();
            if (selected != null) {
                navigateFromSearch(selected);
                stage.close();
            }
        });

        Button closeBtn = new Button("Fermer");
        closeBtn.setStyle("-fx-background-color: #2196F3; -fx-text-fill: white; " +
                "-fx-padding: 8 20; -fx-background-radius: 6; -fx-cursor: hand;");
        closeBtn.setOnAction(e -> stage.close());

        HBox buttonBar = new HBox();
        buttonBar.setAlignment(Pos.CENTER_RIGHT);
        buttonBar.getChildren().add(closeBtn);

        root.getChildren().addAll(titleLabel, listView, buttonBar);
        stage.setScene(new Scene(root));
        stage.show();
    }

    private void navigateFromSearch(String selected) {
        if (selected.contains("Dashboard")) dashboardBtn.fire();
        else if (selected.contains("Événements") && !selected.contains("  ")) eventsToggleBtn.fire();
        else if (selected.contains("Liste événements")) eventsListBtn.fire();
        else if (selected.contains("Catégories")) categoriesBtn.fire();
        else if (selected.contains("Billets")) ticketsBtn.fire();
        else if (selected.contains("Participants") && !selected.contains("  ")) usersToggleBtn.fire();
        else if (selected.contains("Rôles")) rolesBtn.fire();
        else if (selected.contains("Inscriptions")) inscriptionsBtn.fire();
        else if (selected.contains("Sponsors") && !selected.contains("  ")) sponsorsBtn.fire();
        else if (selected.contains("Liste sponsors")) sponsorsListBtn.fire();
        else if (selected.contains("Contrats")) contratsBtn.fire();
        else if (selected.contains("Ressources") && !selected.contains("  ")) resourcesToggleBtn.fire();
        else if (selected.contains("Budget")) budgetBtn.fire();
        else if (selected.contains("Équipements")) equipementsBtn.fire();
        else if (selected.contains("Salles")) sallesBtn.fire();
        else if (selected.contains("Réservations")) reservationsBtn.fire();
        else if (selected.contains("Questionnaires") && !selected.contains("  ")) questionnairesToggleBtn.fire();
        else if (selected.contains("Questions")) questionsBtn.fire();
        else if (selected.contains("Résultats")) reponsesBtn.fire();
        else if (selected.contains("Historique")) showHistorique();
        else if (selected.contains("Passer le Quiz")) showParticipantQuiz();
        else if (selected.contains("Paramètres")) settingsBtn.fire();
    }

    private void showSimpleAlert(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    public void loadEditUserPage(UserModel user) {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/user/editUser.fxml")
            );
            Parent root = loader.load();
            EditUserController controller = loader.getController();
            controller.setMainController(this);
            controller.setUser(user);
            pageContentContainer.getChildren().setAll(root);
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private void updatePageHeader(String pageKey) {
        PageInfo pageInfo = pageInfoMap.get(pageKey);
        if (pageInfo != null) {
            pageTitle.setText(pageInfo.title);
            pageSubtitle.setText(pageInfo.subtitle);
        }
    }

    private void hideAllSubmenus() {
        VBox[] submenus = {eventsSubmenu, usersSubmenu, sponsorsSubmenu, resourcesSubmenu, questionnairesSubmenu};
        Text[] arrows = {eventsArrow, usersArrow, sponsorsArrow, resourcesArrow, questionnairesArrow};

        for (VBox submenu : submenus) {
            if (submenu != null) {
                submenu.setVisible(false);
                submenu.setManaged(false);
            }
        }
        for (Text arrow : arrows) {
            if (arrow != null) arrow.setText("▶");
        }
    }

    private void configureSidebarByRole() {
        UserSession session = UserSession.getInstance();
        String role = session.getRole();

        if (role == null) {
            hideAllButtons();
            return;
        }

        role = role.trim().toLowerCase();
        System.out.println("🔧 Configuration sidebar pour le rôle: " + role);

        hideAllButtons();

        switch (role) {
            case "admin":
            case "admin2":
            case "admin3":
                showAllButtons();
                System.out.println("✅ Admin: tous les boutons affichés");
                break;

            case "organisateur":
            case "organisateur2":
                showAllButtons();
                hideNode(usersToggleBtn);
                hideNode(usersSubmenu);
                System.out.println("✅ Organisateur: tous sauf gestion utilisateurs");
                break;

            case "sponsor":
            case "sponsor2":
            case "sponsor3":
                showOnlySponsorButtons();
                System.out.println("✅ Sponsor: dashboard, sponsors, budget");
                break;

            case "participant":
            case "default":
            case "invité":
                showOnlyParticipantButtons();
                System.out.println("✅ Participant/Default: uniquement dashboard");
                break;

            default:
                hideAllButtons();
                System.out.println("⚠️ Rôle inconnu: " + role);
                break;
        }
    }

    private void showOnlyParticipantButtons() {
        showNode(dashboardBtn);
        hideNode(eventsToggleBtn);
        hideNode(usersToggleBtn);
        hideNode(sponsorsBtn);
        hideNode(resourcesToggleBtn);
        hideNode(questionnairesToggleBtn);
        hideNode(settingsBtn);
        hideNode(budgetBtn);
        hideAllSubmenus();
        System.out.println("✅ Mode participant activé");
    }

    private void hideNode(Node node) {
        if (node != null) {
            node.setVisible(false);
            node.setManaged(false);
        }
    }

    private void showNode(Node node) {
        if (node != null) {
            node.setVisible(true);
            node.setManaged(true);
        }
    }

    private void hideAllButtons() {
        hideNode(dashboardBtn);
        hideNode(sponsorsBtn);
        hideNode(eventsToggleBtn);
        hideNode(usersToggleBtn);
        hideNode(resourcesToggleBtn);
        hideNode(questionnairesToggleBtn);
        hideNode(settingsBtn);
        hideNode(budgetBtn);
    }

    private void showAllButtons() {
        Node[] main = {
                dashboardBtn, eventsToggleBtn, usersToggleBtn, sponsorsBtn,
                resourcesToggleBtn, questionnairesToggleBtn,
                settingsBtn, budgetBtn
        };
        for (Node n : main) showNode(n);
        collapseAllSubmenus();
    }

    private void showOnlySponsorButtons() {
        showNode(dashboardBtn);
        showNode(sponsorsBtn);
        showNode(budgetBtn);
        hideNode(eventsToggleBtn);
        hideNode(usersToggleBtn);
        hideNode(resourcesToggleBtn);
        hideNode(questionnairesToggleBtn);
        hideNode(settingsBtn);
        hideAllSubmenus();
    }

    // ================= MÉTHODES POUR LES ÉVÉNEMENTS =================

    public void showEventsList() {
        System.out.println("📋 Navigation vers Liste des événements");
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/event/event-list.fxml")
            );
            Parent page = loader.load();

            Object controller = loader.getController();
            if (controller instanceof EventListController) {
                ((EventListController) controller).setMainController(this);
                System.out.println("✅ EventListController connecté");
            }

            pageContentContainer.getChildren().clear();
            pageContentContainer.getChildren().add(page);
            updatePageHeader("events");

        } catch (IOException e) {
            System.err.println("❌ Erreur chargement liste événements: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void showEventForm(Event event) {
        try {
            System.out.println("📝 Formulaire événement");

            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/event/event-form.fxml")
            );
            Parent page = loader.load();

            Object controller = loader.getController();
            if (controller instanceof EventFormController) {
                ((EventFormController) controller).setMainController(this);
                if (event != null) {
                    ((EventFormController) controller).setEvent(event);
                }
            }

            pageContentContainer.getChildren().clear();
            pageContentContainer.getChildren().add(page);

        } catch (IOException e) {
            System.err.println("❌ Erreur chargement formulaire événement: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void showEventView(Event event) {
        try {
            System.out.println("👁️ Vue détaillée de l'événement: " + event.getTitle());

            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/event/event-view.fxml")
            );
            Parent page = loader.load();

            Object controller = loader.getController();
            if (controller instanceof EventViewController) {
                ((EventViewController) controller).setMainController(this);
                ((EventViewController) controller).setEvent(event);
            }

            pageContentContainer.getChildren().clear();
            pageContentContainer.getChildren().add(page);

        } catch (IOException e) {
            System.err.println("❌ Erreur chargement vue événement: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void showTicketsList() {
        System.out.println("🎫 Navigation vers Liste des tickets");
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/event/ticket-list.fxml")
            );
            Parent page = loader.load();

            Object controller = loader.getController();
            if (controller instanceof EventTicketListController) {
                ((EventTicketListController) controller).setMainController(this);
                System.out.println("✅ EventTicketListController connecté");
            }

            pageContentContainer.getChildren().clear();
            pageContentContainer.getChildren().add(page);
            updatePageHeader("tickets");

        } catch (IOException e) {
            System.err.println("❌ Erreur chargement liste tickets: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void showTicketView(EventTicket ticket) {
        try {
            System.out.println("👁️ Vue détaillée du ticket: " + ticket.getTicketCode());

            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/event/ticket-view.fxml")
            );
            Parent page = loader.load();

            Object controller = loader.getController();
            if (controller instanceof EventTicketViewController) {
                ((EventTicketViewController) controller).setMainController(this);
                ((EventTicketViewController) controller).setTicket(ticket);
            }

            pageContentContainer.getChildren().clear();
            pageContentContainer.getChildren().add(page);

        } catch (IOException e) {
            System.err.println("❌ Erreur chargement vue ticket: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void showCategories() {
        System.out.println("🗂️ Navigation vers Catégories");
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/event/category-list.fxml")
            );
            Parent page = loader.load();

            Object controller = loader.getController();
            if (controller instanceof CategoryListController) {
                ((CategoryListController) controller).setMainController(this);
                System.out.println("✅ CategoryListController connecté");
            }

            pageContentContainer.getChildren().clear();
            pageContentContainer.getChildren().add(page);
            updatePageHeader("categories");

        } catch (IOException e) {
            System.err.println("❌ Erreur chargement liste catégories: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void showCategoryForm(EventCategory category) {
        try {
            System.out.println("📝 Formulaire catégorie");

            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/event/category-form.fxml")
            );
            Parent page = loader.load();

            Object controller = loader.getController();
            if (controller instanceof CategoryFormController) {
                ((CategoryFormController) controller).setMainController(this);
                if (category != null) {
                    ((CategoryFormController) controller).setCategory(category);
                }
            }

            pageContentContainer.getChildren().clear();
            pageContentContainer.getChildren().add(page);

        } catch (IOException e) {
            System.err.println("❌ Erreur chargement formulaire catégorie: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void showCategoryView(EventCategory category) {
        try {
            System.out.println("👁️ Vue détaillée de la catégorie: " + category.getName());

            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/event/category-view.fxml")
            );
            Parent page = loader.load();

            Object controller = loader.getController();
            if (controller instanceof CategoryViewController) {
                ((CategoryViewController) controller).setMainController(this);
                ((CategoryViewController) controller).setCategory(category);
            }

            pageContentContainer.getChildren().clear();
            pageContentContainer.getChildren().add(page);

        } catch (IOException e) {
            System.err.println("❌ Erreur chargement vue catégorie: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void showTickets() {
        showTicketsList();
    }

    // ================= MÉTHODES DE RAFRAÎCHISSEMENT =================

    public void refreshDashboard() {
        if (dashboardController != null) {
            System.out.println("🔄 Rafraîchissement du dashboard depuis MainController");
            dashboardController.refreshData();
        }
    }

    public void onEventSaved() {
        refreshDashboard();
    }

    public void onParticipantSaved() {
        refreshDashboard();
    }

    public void onInscriptionSaved() {
        refreshDashboard();
    }

    public void cleanup() {
        if (dashboardController != null) {
            dashboardController.cleanup();
        }
    }

    /**
     * Définit le contenu de la page centrale
     */
    public void setContent(Parent node) {
        if (pageContentContainer != null) {
            pageContentContainer.getChildren().setAll(node);
        } else {
            System.err.println("❌ pageContentContainer est null dans setContent()");
        }
    }

    /**
     * Définit le contenu avec titre (compatibilité)
     */
    public void setContent(Parent root, String title) {
        setContent(root, title, "");
    }

    /**
     * Définit le contenu avec titre et sous-titre
     */
    public void setContent(Parent root, String title, String subtitle) {
        if (pageTitle != null) pageTitle.setText(title);
        if (pageSubtitle != null) pageSubtitle.setText(subtitle);
        setContent(root);
    }

    @FXML
    public void showSalles() {
        System.out.println("🏢 Affichage de la page des salles");
        collapseAllSubmenus();
        setActiveButton(sallesBtn);
        loadSallesView();
    }

    @FXML
    public void showEquipements() {
        System.out.println("💻 Affichage de la page des équipements");
        collapseAllSubmenus();
        setActiveButton(equipementsBtn);
        loadEquipementsView();
    }

    @FXML
    public void showReservations() {
        System.out.println("📅 Affichage de la page des réservations");
        collapseAllSubmenus();
        setActiveButton(reservationsBtn);
        loadReservationsView();
    }

    @FXML
    private void handleExit() {
        System.out.println("🚪 Fermeture de l'application");
        System.exit(0);
    }
}