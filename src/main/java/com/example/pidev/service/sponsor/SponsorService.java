package com.example.pidev.service.sponsor;

import com.example.pidev.model.sponsor.Sponsor;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;

public class SponsorService {

    public ObservableList<Sponsor> getAllSponsors() {
        ObservableList<Sponsor> sponsors = FXCollections.observableArrayList();

        // Données de test
        sponsors.add(new Sponsor(1001, 1001, "Google", "https://logo.clearbit.com/google.com",
                5000.0, "sponsor@google.com"));
        sponsors.add(new Sponsor(1002, 1001, "Microsoft", "https://logo.clearbit.com/microsoft.com",
                3500.0, "events@microsoft.com"));
        sponsors.add(new Sponsor(1003, 1002, "IBM", "https://logo.clearbit.com/ibm.com",
                4200.0, "sponsorship@ibm.com"));
        sponsors.add(new Sponsor(1004, 1003, "Red Bull", "https://logo.clearbit.com/redbull.com",
                2500.0, "events@redbull.com"));
        sponsors.add(new Sponsor(1005, 1004, "Adobe", "https://logo.clearbit.com/adobe.com",
                3800.0, "sponsor@adobe.com"));
        sponsors.add(new Sponsor(1006, 1005, "Intel", "https://logo.clearbit.com/intel.com",
                4500.0, "university@intel.com"));
        sponsors.add(new Sponsor(1007, 1006, "Dell", "https://logo.clearbit.com/dell.com",
                3200.0, "sponsor@dell.com"));
        sponsors.add(new Sponsor(1008, 1007, "Amazon AWS", "https://logo.clearbit.com/aws.amazon.com",
                6000.0, "education@amazon.com"));
        sponsors.add(new Sponsor(1009, 1008, "Oracle", "https://logo.clearbit.com/oracle.com",
                2800.0, "events@oracle.com"));
        sponsors.add(new Sponsor(1010, 1009, "Cisco", "https://logo.clearbit.com/cisco.com",
                4100.0, "sponsor@cisco.com"));

        return sponsors;
    }

    public double getTotalContribution() {
        return getAllSponsors().stream()
                .mapToDouble(Sponsor::getContribution_amt)
                .sum();
    }

    public int getTotalSponsors() {
        return getAllSponsors().size();
    }

    public double getAverageContribution() {
        return getTotalContribution() / getTotalSponsors();
    }
}
