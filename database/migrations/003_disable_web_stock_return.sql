-- Stop the web-specific stock restoration when POS cancels a bill.
-- This does not change existing stock or delete the historical deduction records.
IF OBJECT_ID(N'dbo.TR_Web_Return_Stock_On_Cancel', N'TR') IS NOT NULL
    DISABLE TRIGGER dbo.TR_Web_Return_Stock_On_Cancel ON dbo.Fnt_Header;
