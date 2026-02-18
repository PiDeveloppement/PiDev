package com.example.pidev;

import com.example.pidev.controller.role.RoleController;
import com.example.pidev.controller.user.EditUserController;
import com.example.pidev.controller.user.ProfilController;
import com.example.pidev.controller.user.UserController;
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
import javafx.scene.layout.BorderPane;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import javafx.scene.shape.Circle;
import javafx.stage.Modality;
import javafx.stage.Stage;
import com.example.pidev.utils.UserSession;
import com.example.pidev.model.user.UserModel;

import java.io.IOException;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.HashMap;
import java.util.Map;

public class MainController {

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
    private Button eventsToggleBtn;  // Bouton principal Événements
    @FXML
    private Button usersToggleBtn;    // Participants (changé de usersBtn à usersToggleBtn)
    @FXML
    private Button resourcesToggleBtn;
    @FXML
    private Button questionnairesToggleBtn;// Bouton principal Ressources
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
    private TextField globalSearchField; // Ajoutez ce champ
    // Sous-menus Questionnaires
    @FXML
    private VBox questionnairesSubmenu;
    @FXML
    private Button questionsBtn;
    @FXML
    private Button reponsesBtn;
    @FXML
    private Text questionnairesArrow;

    private Map<String, PageInfo> pageInfoMap = new HashMap<>();
    private Button activeButton;

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
        hideAllButtons();     // reset complet
        configureSidebarByRole();
        updateDateTime();
        loadUserProfileInHeader();
        setupGlobalSearch();

        // Charger le dashboard au lieu des catégories
        loadDashboardView();

        // Mettre le bouton dashboard en actif
        if (dashboardBtn != null) {
            setActiveButton(dashboardBtn);
        }
    }

    // ================= GESTION DES TOGGLES =================

    @FXML
    private void toggleEvents() {
        if (eventsSubmenu != null) {
            boolean isVisible = eventsSubmenu.isVisible();
            eventsSubmenu.setVisible(!isVisible);
            eventsSubmenu.setManaged(!isVisible);
            if (eventsArrow != null) {
                eventsArrow.setText(!isVisible ? "▼" : "▶");
            }
        }
    }

    @FXML
    private void toggleUsers() {
        if (usersSubmenu != null) {
            boolean isVisible = usersSubmenu.isVisible();
            usersSubmenu.setVisible(!isVisible);
            usersSubmenu.setManaged(!isVisible);
            if (usersArrow != null) {
                usersArrow.setText(!isVisible ? "▼" : "▶");
            }
        }
    }

    @FXML
    private void toggleSponsors() {
        if (sponsorsSubmenu != null) {
            boolean isVisible = sponsorsSubmenu.isVisible();
            sponsorsSubmenu.setVisible(!isVisible);
            sponsorsSubmenu.setManaged(!isVisible);
            if (sponsorsArrow != null) {
                sponsorsArrow.setText(!isVisible ? "▼" : "▶");
            }
        }
    }

    @FXML
    private void toggleResources() {
        if (resourcesSubmenu != null) {
            boolean isVisible = resourcesSubmenu.isVisible();
            resourcesSubmenu.setVisible(!isVisible);
            resourcesSubmenu.setManaged(!isVisible);
            if (resourcesArrow != null) {
                resourcesArrow.setText(!isVisible ? "▼" : "▶");
            }
        }
    }

    @FXML
    private void toggleQuestionnaires() {
        if (questionnairesSubmenu != null) {
            boolean isVisible = questionnairesSubmenu.isVisible();
            questionnairesSubmenu.setVisible(!isVisible);
            questionnairesSubmenu.setManaged(!isVisible);
            if (questionnairesArrow != null) {
                questionnairesArrow.setText(!isVisible ? "▼" : "▶");
            }
        }
    }

    // ================= GESTION DU PROFIL =================

    private void loadUserProfileInHeader() {
        UserSession session = UserSession.getInstance();
        UserModel currentUser = session.getCurrentUser();

        if (currentUser != null) {
            // Afficher le nom complet
            if (userNameLabel != null) {
                userNameLabel.setText(session.getFullName());
            }

            // Afficher le rôle de l'utilisateur
            if (userRoleLabel != null) {
                String roleName = session.getRole();
                userRoleLabel.setText(roleName);
            }

            // Gérer la photo de profil
            String photoUrl = currentUser.getProfilePictureUrl();
            if (photoUrl != null && !photoUrl.isEmpty()) {
                try {
                    // Charger l'image
                    Image image = new Image(photoUrl, 28, 28, true, true);
                    profileImageView.setImage(image);
                    profileImageView.setVisible(true);

                    // IMPORTANT: Appliquer le clip circulaire
                    Circle clip = new Circle(14, 14, 14);
                    profileImageView.setClip(clip);

                    // Cacher les initiales
                    if (initialsContainer != null) {
                        initialsContainer.setVisible(false);
                    }

                    System.out.println("✅ Image de profil chargée et clip appliqué");

                } catch (Exception e) {
                    System.err.println("Erreur chargement photo: " + e.getMessage());
                    showInitials(session.getInitials());
                }
            } else {
                // Pas de photo, afficher les initiales
                showInitials(session.getInitials());
            }
        } else {
            // Aucun utilisateur connecté
            if (userNameLabel != null) {
                userNameLabel.setText("Invité");
            }
            if (userRoleLabel != null) {
                userRoleLabel.setText("Non connecté");
            }
            showInitials("?");
        }
    }

    public void refreshHeaderProfile() {
        loadUserProfileInHeader();
    }

    private void showInitials(String initials) {
        if (profileImageView != null) {
            profileImageView.setVisible(false);
            // Enlever le clip pour éviter les problèmes
            profileImageView.setClip(null);
        }
        if (initialsContainer != null) {
            initialsContainer.setVisible(true);
            if (userInitialsText != null) {
                userInitialsText.setText(initials);
            }
        }
    }

    /**
     * Applique un clip circulaire à l'image de profil de façon plus robuste
     */
    private void applyCircularClip(ImageView imageView, double radius) {
        if (imageView != null) {
            // S'assurer que l'image est chargée
            if (imageView.getImage() != null) {
                // Créer un cercle avec le bon rayon
                Circle clip = new Circle(radius, radius, radius);

                // Appliquer le clip
                imageView.setClip(clip);

                // Important: maintainer le ratio et ajuster la taille
                imageView.setPreserveRatio(true);

                // Forcer le redimensionnement
                imageView.setFitHeight(radius * 2);
                imageView.setFitWidth(radius * 2);

                System.out.println("✅ Clip circulaire appliqué - Rayon: " + radius);
            } else {
                System.out.println("⚠️ Image non chargée, clip non appliqué");
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
        pageInfoMap.put("questions", new PageInfo("Gestion des questions", "Gérez les questions"));
        pageInfoMap.put("reponses", new PageInfo("Gestion des réponses", "Consultez les réponses"));
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
        if (settingsBtn != null && settingsBtn.getText().equals("Questionnaires")) {
            settingsBtn.setOnAction(e -> {
                toggleQuestionnaires();
                collapseOtherSubmenus("questionnaires");
                setActiveButton(settingsBtn);
            });
        } else if (settingsBtn != null) {
            settingsBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(settingsBtn);
                loadSettingsView();
            });
        }

        // Sous-menus Événements
        if (eventsListBtn != null) {
            eventsListBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(eventsListBtn);
                loadEventView();
            });
        }

        if (categoriesBtn != null) {
            categoriesBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(categoriesBtn);
                loadCategoriesView();
            });
        }

        if (ticketsBtn != null) {
            ticketsBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(ticketsBtn);
                loadTicketsView();
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

        // Sous-menus Questionnaires
        if (questionsBtn != null) {
            questionsBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(questionsBtn);
                loadQuestionsView();
            });
        }

        if (reponsesBtn != null) {
            reponsesBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(reponsesBtn);
                loadReponsesView();
            });
        }

        // Budget
        if (budgetBtn != null && budgetBtn.getText().equals("Budget")) {
            budgetBtn.setOnAction(e -> {
                collapseAllSubmenus();
                setActiveButton(budgetBtn);
                loadBudgetView();
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
        // Liste de tous les boutons
        Button[] allButtons = {
                dashboardBtn, eventsToggleBtn, eventsListBtn, categoriesBtn, ticketsBtn,
                usersToggleBtn, rolesBtn, inscriptionsBtn,
                sponsorsBtn, sponsorsListBtn, contratsBtn,
                resourcesToggleBtn, sallesBtn, equipementsBtn, reservationsBtn,
                settingsBtn, questionsBtn, reponsesBtn,
                budgetBtn
        };

        // Reset all buttons - use CSS classes instead of inline styles
        for (Button btn : allButtons) {
            if (btn != null) {
                // Remove any existing style classes
                btn.getStyleClass().removeAll("main-menu-button", "submenu-button", "sidebar-button-active");

                // Add the appropriate base class based on the button type
                if (btn == eventsListBtn || btn == categoriesBtn || btn == ticketsBtn ||
                        btn == rolesBtn || btn == inscriptionsBtn ||
                        btn == sponsorsListBtn || btn == contratsBtn ||
                        btn == sallesBtn || btn == equipementsBtn || btn == reservationsBtn ||
                        btn == questionsBtn || btn == reponsesBtn || btn == budgetBtn) {
                    btn.getStyleClass().add("submenu-button");
                } else {
                    btn.getStyleClass().add("main-menu-button");
                }
            }
        }

        // Set active button - add the active class
        if (button != null) {
            // Remove any existing active class
            for (Button btn : allButtons) {
                if (btn != null) {
                    btn.getStyleClass().remove("sidebar-button-active");
                }
            }

            // Add active class to the current button
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

            PageInfo pageInfo = pageInfoMap.get(pageKey);
            if (pageInfo != null) {
                pageTitle.setText(pageInfo.title);
                pageSubtitle.setText(pageInfo.subtitle);
            }

            pageContentContainer.getChildren().setAll(page);

        } catch (IOException e) {
            e.printStackTrace();
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
        loadPage("/com/example/pidev/fxml/dashboard/dashboard.fxml", "dashboard");
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

            updatePageHeader("users"); // 👈 AJOUTER CETTE LIGNE

            pageContentContainer.getChildren().setAll(root);
        } catch (Exception e) {
            e.printStackTrace();
        }
    }


    public void loadRoleView() {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/role/role.fxml")
            );
            Parent root = loader.load();

            RoleController controller = loader.getController();
            controller.setMainController(this); // TRÈS IMPORTANT

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

    public void loadSallesView() {
        loadPage("/com/example/pidev/fxml/reservationRessources/salle.fxml", "salles");
    }

    public void loadEquipementsView() {
        loadPage("/com/example/pidev/fxml/reservationRessources/equipement.fxml", "equipements");
    }

    public void loadReservationsView() {
        loadPage("/com/example/pidev/fxml/reservationRessources/reservation.fxml", "reservations");
    }

    public void loadQuestionsView() {
        loadPage("/com/example/pidev/fxml/questionnaire/question.fxml", "questions");
    }

    public void loadReponsesView() {
        loadPage("/com/example/pidev/fxml/questionnaire/reponse.fxml", "reponses");
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
            controller.setEditMode(role); // Mode modification
            controller.setMainController(this);

            pageContentContainer.getChildren().setAll(root);
            updatePageHeader("roles"); // Mettre à jour le titre si nécessaire

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
            controller.setAddMode(); // Mode ajout
            controller.setMainController(this);

            pageContentContainer.getChildren().setAll(root);
            updatePageHeader("roles"); // Mettre à jour le titre si nécessaire

        } catch (Exception e) {
            e.printStackTrace();
            showSimpleAlert("Erreur", "Impossible de charger la page d'ajout: " + e.getMessage());
        }
    }

    @FXML
    public void logout() {
        try {
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
        System.out.println("🔄 Rafraîchissement de la sidebar pour le nouveau rôle");


        // Réinitialiser l'affichage des sous-menus
        collapseAllSubmenus();
    }
////////////boutton Recherche Globale///////////////


    // Ajoutez ce champ avec les autres @FXML


    // Ajoutez cette méthode dans votre MainController
    private void setupGlobalSearch() {
        if (globalSearchField != null) {
            // Recherche quand on appuie sur Entrée
            globalSearchField.setOnAction(event -> {
                String query = globalSearchField.getText().trim();
                if (!query.isEmpty()) {
                    performSimpleSearch(query);
                }
            });

            // Optionnel: effacer avec Escape
            globalSearchField.setOnKeyPressed(event -> {
                if (event.getCode() == KeyCode.ESCAPE) {
                    globalSearchField.clear();
                }
            });
        }
    }

    // Méthode de recherche simplifiée
    private void performSimpleSearch(String query) {
        String lowerQuery = query.toLowerCase();

        // Créer une liste simple de résultats
        ObservableList<String> results = FXCollections.observableArrayList();

        // 1. Chercher dans les menus de la sidebar
        if ("dashboard".contains(lowerQuery)) {
            results.add("📊 Dashboard");
        }
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
        }
        if ("questionnaires".contains(lowerQuery)) {
            results.add("📝 Questionnaires");
            results.add("   ❓ Questions");
            results.add("   📊 Réponses");
        }
        if ("paramètres".contains(lowerQuery) || "settings".contains(lowerQuery)) {
            results.add("⚙️ Paramètres");
        }

        // Afficher les résultats
        if (results.isEmpty()) {
            showSimpleAlert("Aucun résultat", "Aucun résultat trouvé pour: " + query);
        } else {
            showSimpleResultsPopup(query, results);
        }
    }

    // Popup simple pour les résultats
    private void showSimpleResultsPopup(String query, ObservableList<String> results) {
        // Créer une nouvelle fenêtre
        Stage stage = new Stage();
        stage.setTitle("Résultats de recherche");
        stage.initModality(Modality.APPLICATION_MODAL);

        VBox root = new VBox(15);
        root.setStyle("-fx-padding: 20; -fx-background-color: white;");
        root.setPrefWidth(400);
        root.setMaxWidth(400);

        // En-tête
        Label titleLabel = new Label("Résultats pour: \"" + query + "\"");
        titleLabel.setStyle("-fx-font-size: 14px; -fx-font-weight: bold; -fx-text-fill: #0D47A1;");

        // Liste des résultats
        ListView<String> listView = new ListView<>(results);
        listView.setPrefHeight(Math.min(300, results.size() * 40));
        listView.setStyle("-fx-background-color: transparent;");

        // Action au clic
        listView.setOnMouseClicked(event -> {
            String selected = listView.getSelectionModel().getSelectedItem();
            if (selected != null) {
                navigateFromSearch(selected);
                stage.close();
            }
        });

        // Bouton fermer
        Button closeBtn = new Button("Fermer");
        closeBtn.setStyle("-fx-background-color: #2196F3; -fx-text-fill: white; " +
                "-fx-padding: 8 20; -fx-background-radius: 6; -fx-cursor: hand;");
        closeBtn.setOnAction(e -> stage.close());

        HBox buttonBar = new HBox();
        buttonBar.setAlignment(Pos.CENTER_RIGHT);
        buttonBar.getChildren().add(closeBtn);

        root.getChildren().addAll(titleLabel, listView, buttonBar);

        Scene scene = new Scene(root);
        stage.setScene(scene);
        stage.show();
    }

    // Navigation depuis les résultats
    private void navigateFromSearch(String selected) {
        if (selected.contains("Dashboard")) {
            dashboardBtn.fire();
        } else if (selected.contains("Événements") && !selected.contains("  ")) {
            eventsToggleBtn.fire();
        } else if (selected.contains("Liste événements")) {
            eventsListBtn.fire();
        } else if (selected.contains("Catégories")) {
            categoriesBtn.fire();
        } else if (selected.contains("Billets")) {
            ticketsBtn.fire();
        } else if (selected.contains("Participants") && !selected.contains("  ")) {
            usersToggleBtn.fire();
        } else if (selected.contains("Rôles")) {
            rolesBtn.fire();
        } else if (selected.contains("Inscriptions")) {
            inscriptionsBtn.fire();
        } else if (selected.contains("Sponsors") && !selected.contains("  ")) {
            sponsorsBtn.fire();
        } else if (selected.contains("Liste sponsors")) {
            sponsorsListBtn.fire();
        } else if (selected.contains("Contrats")) {
            contratsBtn.fire();
        } else if (selected.contains("Ressources") && !selected.contains("  ")) {
            resourcesToggleBtn.fire();
        } else if (selected.contains("Budget")) {
            budgetBtn.fire();
        } else if (selected.contains("Équipements")) {
            equipementsBtn.fire();
        } else if (selected.contains("Salles")) {
            sallesBtn.fire();
        } else if (selected.contains("Questionnaires") && !selected.contains("  ")) {
            questionnairesToggleBtn.fire();
        } else if (selected.contains("Questions")) {
            questionsBtn.fire();
        } else if (selected.contains("Réponses")) {
            reponsesBtn.fire();
        } else if (selected.contains("Paramètres")) {
            settingsBtn.fire();
        }
    }

    // Alerte simple
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

            controller.setMainController(this); // ⭐
            controller.setUser(user);           // ⭐⭐ passer l'utilisateur

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
        VBox[] submenus = {
                eventsSubmenu, usersSubmenu, sponsorsSubmenu,
                resourcesSubmenu, questionnairesSubmenu
        };

        Text[] arrows = {
                eventsArrow, usersArrow, sponsorsArrow,
                resourcesArrow, questionnairesArrow
        };

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
            case "default":      // 👈 DEFAULT = PARTICIPANT (uniquement dashboard)
            case "invité":
                showOnlyParticipantButtons();
                System.out.println("✅ Participant/Default: uniquement dashboard");
                break;

            default:
                hideAllButtons(); // sécurité max
                System.out.println("⚠️ Rôle inconnu: " + role + " - aucun bouton");
                break;
        }
    }
    private void showOnlyParticipantButtons() {
        // Participant voit uniquement le dashboard
        showNode(dashboardBtn);

        // Cache tous les autres boutons
        hideNode(eventsToggleBtn);
        hideNode(usersToggleBtn);
        hideNode(sponsorsBtn);
        hideNode(resourcesToggleBtn);
        hideNode(questionnairesToggleBtn);
        hideNode(settingsBtn);
        hideNode(budgetBtn);

        // Cache tous les sous-menus
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



}