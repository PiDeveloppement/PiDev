package com.example.pidev.service.sponsor;

import com.example.pidev.model.sponsor.Sponsor;
import com.example.pidev.utils.MyDatabase;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;

import java.sql.*;

public class SponsorService {

    private Connection cnx() {
        return MyDatabase.getConnectionInstance();
    }

    /**
     * Tant que l'intégration "event" n'est pas prête, on ne bloque pas.
     * Si la table event existe, on vérifie, sinon on laisse passer.
     */
    public boolean eventExists(int eventId) {
        String sql = "SELECT 1 FROM `event` WHERE id = ? LIMIT 1";
        try (PreparedStatement ps = cnx().prepareStatement(sql)) {
            ps.setInt(1, eventId);
            try (ResultSet rs = ps.executeQuery()) {
                return rs.next();
            }
        } catch (SQLException e) {
            // table event non dispo / pas d'intégration => ne bloque pas
            return true;
        }
    }

    public boolean sponsorExistsById(int id) {
        String sql = "SELECT 1 FROM sponsor WHERE id=? LIMIT 1";
        try (PreparedStatement ps = cnx().prepareStatement(sql)) {
            ps.setInt(1, id);
            try (ResultSet rs = ps.executeQuery()) {
                return rs.next();
            }
        } catch (SQLException e) {
            throw new RuntimeException("sponsorExistsById failed", e);
        }
    }

    // ---------- LISTES ----------
    public ObservableList<Sponsor> getAllSponsors() {
        ObservableList<Sponsor> list = FXCollections.observableArrayList();
        String sql = "SELECT id, event_id, company_name, logo_url, contact_email, contribution_name, contract_url FROM sponsor";
        try (PreparedStatement ps = cnx().prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {

            while (rs.next()) {
                list.add(new Sponsor(
                        rs.getInt("id"),
                        rs.getInt("event_id"),
                        rs.getString("company_name"),
                        rs.getString("logo_url"),
                        rs.getDouble("contribution_name"),
                        rs.getString("contact_email"),
                        rs.getString("contract_url")
                ));
            }
            return list;

        } catch (SQLException e) {
            throw new RuntimeException("getAllSponsors failed", e);
        }
    }

    public ObservableList<Sponsor> getSponsorsByEmail(String email) {
        ObservableList<Sponsor> list = FXCollections.observableArrayList();
        String sql = "SELECT id, event_id, company_name, logo_url, contact_email, contribution_name, contract_url " +
                "FROM sponsor WHERE contact_email = ?";

        try (PreparedStatement ps = cnx().prepareStatement(sql)) {
            ps.setString(1, email);

            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    list.add(new Sponsor(
                            rs.getInt("id"),
                            rs.getInt("event_id"),
                            rs.getString("company_name"),
                            rs.getString("logo_url"),
                            rs.getDouble("contribution_name"),
                            rs.getString("contact_email"),
                            rs.getString("contract_url")
                    ));
                }
            }
            return list;

        } catch (SQLException e) {
            throw new RuntimeException("getSponsorsByEmail failed", e);
        }
    }

    // ✅ POUR PORTAL (ComboBox emails)
    public ObservableList<String> getSponsorEmails() {
        ObservableList<String> list = FXCollections.observableArrayList();
        String sql = "SELECT DISTINCT contact_email FROM sponsor " +
                "WHERE contact_email IS NOT NULL AND contact_email <> '' ORDER BY contact_email";

        try (PreparedStatement ps = cnx().prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {

            while (rs.next()) list.add(rs.getString(1));
            return list;

        } catch (SQLException e) {
            throw new RuntimeException("getSponsorEmails failed", e);
        }
    }

    public ObservableList<String> getCompanyNamesByEmail(String email) {
        ObservableList<String> list = FXCollections.observableArrayList();
        String sql = "SELECT DISTINCT company_name FROM sponsor " +
                "WHERE contact_email = ? AND company_name IS NOT NULL AND company_name <> '' ORDER BY company_name";

        try (PreparedStatement ps = cnx().prepareStatement(sql)) {
            ps.setString(1, email);

            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) list.add(rs.getString(1));
            }
            return list;

        } catch (SQLException e) {
            throw new RuntimeException("getCompanyNamesByEmail failed", e);
        }
    }

    public ObservableList<Integer> getEventIdsByEmail(String email) {
        ObservableList<Integer> list = FXCollections.observableArrayList();
        String sql = "SELECT DISTINCT event_id FROM sponsor WHERE contact_email = ? ORDER BY event_id";

        try (PreparedStatement ps = cnx().prepareStatement(sql)) {
            ps.setString(1, email);

            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) list.add(rs.getInt(1));
            }
            return list;

        } catch (SQLException e) {
            // si event_id pas propre, ne casse pas
            return FXCollections.observableArrayList();
        }
    }

    public int getMySponsorsCount(String email) {
        String sql = "SELECT COUNT(*) FROM sponsor WHERE contact_email = ?";
        try (PreparedStatement ps = cnx().prepareStatement(sql)) {
            ps.setString(1, email);
            try (ResultSet rs = ps.executeQuery()) {
                rs.next();
                return rs.getInt(1);
            }
        } catch (SQLException e) {
            throw new RuntimeException("getMySponsorsCount failed", e);
        }
    }

    public double getMyTotalContribution(String email) {
        String sql = "SELECT COALESCE(SUM(contribution_name), 0) FROM sponsor WHERE contact_email = ?";
        try (PreparedStatement ps = cnx().prepareStatement(sql)) {
            ps.setString(1, email);
            try (ResultSet rs = ps.executeQuery()) {
                rs.next();
                return rs.getDouble(1);
            }
        } catch (SQLException e) {
            throw new RuntimeException("getMyTotalContribution failed", e);
        }
    }

    public int getMySponsoredEventsCount(String email) {
        String sql = "SELECT COUNT(DISTINCT event_id) FROM sponsor WHERE contact_email = ?";
        try (PreparedStatement ps = cnx().prepareStatement(sql)) {
            ps.setString(1, email);
            try (ResultSet rs = ps.executeQuery()) {
                rs.next();
                return rs.getInt(1);
            }
        } catch (SQLException e) {
            return 0;
        }
    }

    // ---------- ✅ ADMIN HELPERS (pour SponsorAdminController) ----------
    public ObservableList<String> getCompanyNamesAll() {
        ObservableList<String> list = FXCollections.observableArrayList();
        String sql = "SELECT DISTINCT company_name FROM sponsor " +
                "WHERE company_name IS NOT NULL AND company_name<>'' ORDER BY company_name";

        try (PreparedStatement ps = cnx().prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {

            while (rs.next()) list.add(rs.getString(1));
            return list;

        } catch (SQLException e) {
            throw new RuntimeException("getCompanyNamesAll failed", e);
        }
    }

    public ObservableList<Integer> getEventIdsAll() {
        ObservableList<Integer> list = FXCollections.observableArrayList();
        String sql = "SELECT DISTINCT event_id FROM sponsor ORDER BY event_id";

        try (PreparedStatement ps = cnx().prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {

            while (rs.next()) list.add(rs.getInt(1));
            return list;

        } catch (SQLException e) {
            return FXCollections.observableArrayList();
        }
    }

    public int getTotalSponsors() {
        String sql = "SELECT COUNT(*) FROM sponsor";
        try (PreparedStatement ps = cnx().prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {

            rs.next();
            return rs.getInt(1);

        } catch (SQLException e) {
            throw new RuntimeException("getTotalSponsors failed", e);
        }
    }

    public double getTotalContribution() {
        String sql = "SELECT COALESCE(SUM(contribution_name),0) FROM sponsor";
        try (PreparedStatement ps = cnx().prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {

            rs.next();
            return rs.getDouble(1);

        } catch (SQLException e) {
            throw new RuntimeException("getTotalContribution failed", e);
        }
    }

    public double getAverageContribution() {
        String sql = "SELECT COALESCE(AVG(contribution_name),0) FROM sponsor";
        try (PreparedStatement ps = cnx().prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {

            rs.next();
            return rs.getDouble(1);

        } catch (SQLException e) {
            throw new RuntimeException("getAverageContribution failed", e);
        }
    }

    // ---------- CRUD ----------
    public void addSponsor(Sponsor s) {
        String sql = "INSERT INTO sponsor (event_id, company_name, logo_url, contact_email, contribution_name, contract_url) " +
                "VALUES (?, ?, ?, ?, ?, ?)";

        try (PreparedStatement ps = cnx().prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            ps.setInt(1, s.getEvent_id());
            ps.setString(2, s.getCompany_name());
            ps.setString(3, s.getLogo_url());
            ps.setString(4, s.getContact_email());
            ps.setDouble(5, s.getContribution_name());
            ps.setString(6, s.getContract_url());

            ps.executeUpdate();

            try (ResultSet keys = ps.getGeneratedKeys()) {
                if (keys.next()) s.setId(keys.getInt(1)); // ✅ auto-increment
            }

        } catch (SQLException e) {
            throw new RuntimeException("insert sponsor failed", e);
        }
    }

    public void updateSponsor(Sponsor s) {
        if (!sponsorExistsById(s.getId())) {
            throw new IllegalArgumentException("Sponsor introuvable (id=" + s.getId() + ")");
        }

        String sql = "UPDATE sponsor SET event_id=?, company_name=?, logo_url=?, contact_email=?, contribution_name=?, contract_url=? " +
                "WHERE id=?";

        try (PreparedStatement ps = cnx().prepareStatement(sql)) {
            ps.setInt(1, s.getEvent_id());
            ps.setString(2, s.getCompany_name());
            ps.setString(3, s.getLogo_url());
            ps.setString(4, s.getContact_email());
            ps.setDouble(5, s.getContribution_name());
            ps.setString(6, s.getContract_url());
            ps.setInt(7, s.getId());

            int updated = ps.executeUpdate();
            if (updated == 0) throw new RuntimeException("Aucune ligne modifiée: " + s.getId());

        } catch (SQLException e) {
            throw new RuntimeException("update sponsor failed", e);
        }
    }

    public void deleteSponsor(int id) {
        String sql = "DELETE FROM sponsor WHERE id=?";
        try (PreparedStatement ps = cnx().prepareStatement(sql)) {
            ps.setInt(1, id);
            ps.executeUpdate();
        } catch (SQLException e) {
            throw new RuntimeException("delete sponsor failed", e);
        }
    }

    public void updateContractUrl(int sponsorId, String contractUrl) {
        String sql = "UPDATE sponsor SET contract_url=? WHERE id=?";
        try (PreparedStatement ps = cnx().prepareStatement(sql)) {
            ps.setString(1, contractUrl);
            ps.setInt(2, sponsorId);
            ps.executeUpdate();
        } catch (SQLException e) {
            throw new RuntimeException("updateContractUrl failed", e);
        }
    }
}
